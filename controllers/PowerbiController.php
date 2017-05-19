<?php

namespace app\controllers;

use Yii;
use app\models\Workspace;
use app\models\Collection;
use app\models\Dataset;
use yii\web\UploadedFile;
use app\models\Dashboard;

class PowerbiController extends \yii\web\Controller
{
    public function actionConnect()
    {
		//test commit
        return $this->render('connect');
    }
	
	/**
	* @creating workspace
	* @returns workspace_id
	*/
    public function actionCreateWorkspace()
    {
		$workspace      = new Workspace();  
		$collections 	= Collection::find()->all();
                
		if($workspace->load(Yii::$app->request->post())){
            $collection 	= Collection::findOne($workspace->collection_id);
			$end_url		='https://api.powerbi.com/v1.0/collections/';
            $end_url        .= $collection->collection_name;
            $end_url        .='/workspaces';
			$access_key	= $collection->AppKey;
			$params = "name={$workspace->workspace_name}";
                        $response       = json_decode($workspace->doCurl_POST($end_url,$access_key,$params,"application/x-www-form-urlencoded","POST"));
                        if(isset($response->error)){
                            //flash error message
                            Yii::$app->session->setFlash('some_error',  $response->error->message);
                            return $this->render('create-workspace',[
								'model'=>$workspace,
                                'collections' => $collections,
                            ]);
                        }
                        $workspace->workspace_id = $response->workspaceId;
						$workspace->save(false);

                        return $this->redirect(['workspace/index']);
		}
		else
		{
			return $this->render('create-workspace',[
				'model'=>$workspace,
                'collections' => $collections,
			]);
		}
    }
	
	/**
	* @create Dataset
	* @returns Dataset_id,datasource_id,gateway_id
	*/
	public function actionCreateDataset()
    {
		$dataset      	= new Dataset();  
		$dashboard		= new Dashboard();
		$collections	= Collection::find()->all();
		$workspaces		= Workspace::find()->all();
                
		if($dataset->load(Yii::$app->request->post())){
            $workspace	 	= Workspace::findOne($dataset->workspace_id);
			$collection 	= Collection::findOne($dataset->collection_id);
			$uploadedFile   = UploadedFile::getInstance($dataset, 'file');
			
			//Saving the file to local directory for cURL access.
			$uploadedFile->saveAs('uploads/'.$uploadedFile->name);
			
			//request URL which returns dataset id.
			$end_url		='https://api.powerbi.com/v1.0/collections/';
            $end_url        .= $collection->collection_name;
            $end_url        .='/workspaces/'.$workspace->workspace_id.'/imports?datasetDisplayName='.$dataset->dataset_name;
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
                        $dataset->dataset_id 	= $response->id;
						$dataset->workspace_id	= $workspace->w_id;
						
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
							$dataset->datasource_id = $gateway->id;
							$dataset->gateway_id 	= $gateway->gatewayId; 
							$dataset->save(false);
							
							$dashboard->dashboard_name 	= $dataset->dashboard_name;
							$dashboard->pbix_file	 	= 'uploads/'.$uploadedFile->name;
							$dashboard->description 	= $dataset->description;
							$dashboard->save(false);
							
							//PATCH
							$patchurl="https://api.powerbi.com/v1.0/collections/".$collection->collection_name."/workspaces/".$workspace->workspace_id."/gateways/".$gateway->gatewayId."/datasources/".$dataset->datasource_id;
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
						
                        return $this->redirect(['dataset/index']);
		}
		else
		{
			return $this->render('create-dataset',[
				'model'			=> $dataset,
                'workspaces' 	=> $workspaces,
				'collections'	=> $collections,
			]);
		}
	}

    public function actionImport()
    {
        return $this->render('import');
    }

    public function actionReport()
    {
        return $this->render('report');
    }

    
}
