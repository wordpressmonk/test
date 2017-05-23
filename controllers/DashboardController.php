<?php

namespace app\controllers;

use Yii;
use app\models\Dashboard;
use app\models\search\Dashboard as DashboardSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\Workspace;
use app\models\Collection;
use app\models\DataModel;
use yii\web\UploadedFile;
/**
 * DashboardController implements the CRUD actions for Dashboard model.
 */
class DashboardController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Dashboard models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DashboardSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
		isset($_REQUEST['workspace_id'])?$dataProvider->query->andFilterWhere(['workspace_id'=>$_REQUEST['workspace_id']]):'';
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Dashboard model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Dashboard model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        set_time_limit(0);
		$model = new Dashboard();
		$collections	= Collection::find()->all();
		$workspaces		= Workspace::find()->all();
		
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			
			$model->file = UploadedFile::getInstance($model, 'file');
			if ($model->upload() 
				//&& $model->save()
			) {
                // file is uploaded successfully
				$data = \moonland\phpexcel\Excel::import(\Yii::$app->basePath."/web/uploads/". $model->file->baseName . '.' . $model->file->extension, [
					'setFirstRecordAsKeys' => true, 
					'setIndexSheetByName' => true, 
				]);
				$tables = [];
				//print_r($data);die;
				foreach($data as $key=>$sheets){					
					$datamodel = new DataModel();
					$datamodel->model_name = $model->prefix."_".$key;
					$tables[] = $datamodel->model_name;
					if(!isset($sheets[0])){
						$model->addError("file","Excel file requires atleast one sheet.");
						return $this->render('create', [
							'model' => $model,
							'collections' => $collections,
							'workspaces' => $workspaces
						]);
					}
					$headers = $sheets[0];
					$attributes = [];
					foreach($headers as $header=>$value){
						if($header!=''){
							if((strtolower($header) == 'id'))
								$attributes[] = ['field_name'=>$header,'field_type'=>'integer'];
							else $attributes[] = ['field_name'=>$header,'field_type'=>'text'];							
						}
					}
					
					$datamodel->attributes = serialize($attributes);
					if(!empty($headers)&& $datamodel->save()){
						// save data too
						foreach($sheets as $header=>$data){
							foreach($data as $key=>$d){
								//eliminate the null keys
								if($key == '')
									unset($data[$key]);
							}
							$data['eq_customer_id'] = \Yii::$app->user->id;
							\Yii::$app->db->createCommand()
								->insert($datamodel->model_name, $data)->execute();
						}
					}
					$model->models = serialize($tables);
					$model->save();
				}
				return $this->redirect(['view', 'id' => $model->dashboard_id]);
            }						
            
        } else {
            return $this->render('create', [
                'model' => $model,
				'collections' => $collections,
				'workspaces' => $workspaces
            ]);
        }
    }
	
	
	/**
	*
	* Uploading the pbix file
	*/
	
	public function actionAddpbix()
	{
		$dashboard		= new Dashboard();
		$collections	= Collection::find()->all();
		$workspaces		= Workspace::find()->all();
                
		if($dashboard->load(Yii::$app->request->post())){
			$dashboard1		= Dashboard::findOne($dashboard->dashboard_id);
            $workspace	 	= Workspace::findOne($dashboard1->workspace_id);
			$collection 	= Collection::findOne($workspace->collection_id);
			$uploadedFile   = UploadedFile::getInstance($dashboard, 'file');
			
			//Saving the file to local directory for cURL access.
			$uploadedFile->saveAs('uploads/'.$uploadedFile->name);
			
			//request URL which returns dataset id.
			$end_url		='https://api.powerbi.com/v1.0/collections/';
            $end_url        .= $collection->collection_name;
            $end_url        .='/workspaces/'.$workspace->workspace_id.'/imports?datasetDisplayName='.$dashboard1->dashboard_name;
			$access_key		= $collection->AppKey;
			
			//create file which can access via cURL.
			$curl_file = curl_file_create(\Yii::$app->basePath.'/web/uploads/'.$uploadedFile->name,'pbix',$uploadedFile->baseName);
			$params = ['file' => $curl_file];
		
            $response	= json_decode($workspace->doCurl_POST($end_url,$access_key,$params,"multipart/form-data","POST"));
                        if(isset($response->error->message)){
                            //flash error message
                            Yii::$app->session->setFlash('some_error',  $response->error->message);
                            return $this->render('create-dataset',[
								'model'=>$dataset,
                                'workspaces' => $workspaces,
                            ]);
                        }
                        $dashboard1->dataset_id 	= $response->id;
						$dashboard1->workspace_id	= $workspace->w_id;
						
						//The request URL which returns the dataset id of the workspace
						//if use above dataset_id the datasource response is Datasource ID missing.We are the below dataset for the next request.
						$url = 'https://api.powerbi.com/v1.0/collections/'.$collection->collection_name.'/workspaces/'.$workspace->workspace_id.'/datasets';
						$respns_dtast = json_decode($workspace->doCurl_GET($url,$access_key));
						if(isset($respns_dtast->error->message)){
                            //flash error message
                            Yii::$app->session->setFlash('some_error',  $respns_dtast->error->message);
                            return $this->render('create-dataset',[
								'model'=>$dataset,
                                'workspaces' => $workspaces,
                            ]);
                        }
						foreach($respns_dtast->value as $datasets)
						{
							//Returns the datasource id,gateway id
							$end_url ='https://api.powerbi.com/v1.0/collections/'.$collection->collection_name.'/workspaces/'.$workspace->workspace_id.'/datasets/'.$datasets->id.'/Default.GetBoundGatewayDatasources';

							$respns_ds_gw = json_decode($workspace->doCurl_GET($end_url,$access_key));
							if(isset($respns_ds_gw->error->message)){
								//flash error message
								Yii::$app->session->setFlash('some_error',  $respns_ds_gw->error->message);
								return $this->render('create-dataset',[
									'model'=>$dataset,
									'workspaces' => $workspaces,
								]);
							}
							if(isset($respns_ds_gw->value))
							{
							foreach($respns_ds_gw->value as $gateway)
							{
							$dashboard1->datasource_id 	= $gateway->id;
							$dashboard1->gateway_id 	= $gateway->gatewayId; 
							$dashboard1->pbix_file	 	= 'uploads/'.$uploadedFile->name;
							$dashboard1->save(false);
							
							//PATCH
							$patchurl="https://api.powerbi.com/v1.0/collections/".$collection->collection_name."/workspaces/".$workspace->workspace_id."/gateways/".$gateway->gatewayId."/datasources/".$dashboard1->datasource_id;
							$params = json_encode([
							"credentialType"=>"Basic",
								"basicCredentials"=>[
								"username"=>"eqvision",
								"password"=>"Al@inno17!",
								]
							]);
							$respns_patch = json_decode($workspace->doCurl_POST($patchurl,$access_key,$params,"application/json","PATCH"));
							}
							}
						
						}
						
                        return $this->redirect(['dashboard/index']);
		}
		else
		{
			return $this->render('addpbix',[
				'model'			=> $dashboard,
                'workspaces' 	=> $workspaces,
				'collections'	=> $collections,
			]);
		}
	}

    /**
     * Updates an existing Dashboard model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->dashboard_id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Dashboard model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Dashboard model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Dashboard the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Dashboard::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
