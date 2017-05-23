<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel app\models\search\Dashboard */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Dashboards';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="dashboard-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Add Dashboard', ['create'], ['class' => 'btn btn-success',
			'data'=>[
				'method'=>'POST',
				'params'=>[
					'workspace_id'=>isset($_REQUEST['workspace_id'])?$_REQUEST['workspace_id']:'',
					'collection_id'=>isset($_REQUEST['collection_id'])?$_REQUEST['collection_id']:'',
				],
			]
		]) ?>
    </p>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'dashboard_id',
            'dashboard_name',
            //'pbix_file:ntext',
			[
			'label' =>'Pbix File',
			'format'=>'raw',
			'value'=>function($data){
				return !empty($data['pbix_file'])?(Html::a($data->pbix_file,[\Yii::$app->basePath.'/web/'.$data->pbix_file],['download'=>'download'])):(Html::a('Add file',['dashboard/addpbix'],['data'=>[
					'method'=>'POST',
					'params'=>[
						'dashboard_id'=>$data->dashboard_id,
					]
				]
				]));
			}
			],
            'description',
            //'models:ntext',
            // 'report_id:ntext',
            // 'form_data:ntext',
            'workspace.workspace_name',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>
</div>
