<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Http\Controller;

use app\Modules\Official\Article\Service\ArticleLogic;
use app\Modules\Official\Article\Validation\ArticleValidate;

class ArticleController extends AbstractArticleCrudController
{
    protected const CRUD_LOGIC = ArticleLogic::class;
    protected const CRUD_VALIDATE = ArticleValidate::class;
}
