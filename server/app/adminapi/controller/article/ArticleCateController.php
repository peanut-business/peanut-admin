<?php
declare(strict_types=1);

namespace app\adminapi\controller\article;

use app\adminapi\logic\article\ArticleCateLogic;
use app\adminapi\validate\article\ArticleCateValidate;
use think\response\Json;

class ArticleCateController extends AbstractArticleCrudController
{
    protected const CRUD_LOGIC = ArticleCateLogic::class;
    protected const CRUD_VALIDATE = ArticleCateValidate::class;

    public function all(): Json
    {
        return $this->data(ArticleCateLogic::all($this->resolveCrudContext()));
    }
}
