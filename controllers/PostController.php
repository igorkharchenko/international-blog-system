<?php

namespace app\controllers;

use Yii;
use app\models\Post;
use app\models\Comment;
use app\models\User;
use yii\data\ActiveDataProvider;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\data\Pagination;
use yii\db\ActiveQuery;

/**
 * PostController implements the CRUD actions for Post model.
 */
class PostController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Lists all Post models.
     * @return mixed
     */
    public function actionIndex()
    {
        $query = Post::find();

        $pagination = new Pagination([
            'defaultPageSize' => 5,
            'totalCount' => $query
                        ->where('publish_status=:publish', [':publish' => 'publish'])
                        ->count(),
        ]);

        $posts = $query
            ->where('publish_status=:publish', [':publish' => 'publish'])
            ->orderBy('publish_date')
            ->offset($pagination->offset)
            ->limit($pagination->limit)
            ->all();

        return $this->render('@app/views/post/index.php',[
            'posts' => $posts,
            'pagination' => $pagination,
        ]);
    }

    /**
     * Displays a single Post model.
     * @param string $id
     * @return mixed
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);

        // find all comments with [comment.post_id == post.id]
        $comment_model = new Comment;
        $comment_query = Comment::find()
            ->where(['post_id' => $id]);

        $comments_pagination = new Pagination([
            'defaultPageSize' => 5,
            'totalCount' => $comment_query->count(),
        ]);
        $comments = $comment_model->getPostComments($id, $comments_pagination);
        $comments_count = $comment_query->count();

        $tmp_post = new Post();
            $post_author = $tmp_post->findAuthorUsername($id); // find author username
            $hasPrivilegies_Post = $tmp_post->checkUDPrivilegies($model); // check user permissions
        unset($tmp_post);

        # if comment is loaded
        if ($comment_model->load(Yii::$app->request->post())) {
            $comment_model->getData();
            return $this->redirect(['view','id' => $id]);
        } else {
            return $this->render('view', [
                'model' => $model,
                'post_author' => $post_author,
                'hasPrivilegies_Post' => $hasPrivilegies_Post,
                'comments' => $comments,
                'comments_count' => $comments_count,
                'comments_pagination' => $comments_pagination,
            ]);
        }
    }

    /**
     * Displays all posts which belongs to current user
     * @param string $status Status of the post: draft or publish
     * @return mixed
     */
    public function actionOurposts($status='all')
    {
        $model = new Post;
        $query = Post::find();

        if($status == 'all') {
            $posts = $query
                ->where('author_id=:author_id', [':author_id' => Yii::$app->user->id]);
        } else {
            $posts = $query
                ->where('publish_status=:status', [':status' => $status])
                ->andWhere('author_id=:author_id', [':author_id' => Yii::$app->user->id]);
        }
        $pagination = new Pagination([
            'defaultPageSize' => 5,
            'totalCount' => $posts->count(),
        ]);


        return $this->render('ourPosts', [
            'model' => $model,
            'posts' => $posts->all(),
            'pagination' => $pagination,
        ]);
    }

    /**
     * Creates a new Post model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new Post();

        if($model->checkCPrivilegies()) {
            if ($model->load(\Yii::$app->request->post())) {
                $model->getPostData();
                return $this->redirect(['index', 'id' => $model->id]);
            } else {
                return $this->render('create', [
                    'model' => $model,
                ]);
            }
        }
        else return $this->redirect('@app/views/site/error_user.php');
    }

    /**
     * Updates an existing Post model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        // If this is an author or an admin
        if($model->checkUDPrivilegies($model)) {

            if ($model->load(Yii::$app->request->post()) && $model->save()) {
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                return $this->render('update', [
                    'model' => $model,
                ]);
            }
        } else {
            return $this->redirect('@app/views/site/login.php');
        }
    }

    /**
     * Deletes an existing Post model.
     * With the post deletion all the comments deletion is needed too.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param string $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        // If this is an author or an admin
        if($model->checkUDPrivilegies($model)) {
            Comment::deleteAll('post_id=:post_id', [':post_id' => $id]);
            $model->delete();
            return $this->redirect(['index']);
        } else {
            return $this->redirect('@app/views/site/login.php');
        }
    }

    /**
     * Finds the Post model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return Post the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Post::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }
}
