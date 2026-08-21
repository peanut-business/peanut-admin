<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\logic\article\ArticleLogic;
use app\adminapi\validate\article\ArticleValidate;

class ArticleController extends AbstractArticleCrudController
{
    protected const CRUD_LOGIC = ArticleLogic::class;
    protected const CRUD_VALIDATE = ArticleValidate::class;
}
