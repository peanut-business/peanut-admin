<?php
declare(strict_types=1);

namespace app\Modules\Official\Article\Http\Controller;

use app\Modules\Official\Article\Validation\ArticleCateValidate;
use app\common\http\PageResult;
use PeanutAdmin\Kernel\Auth\TenantContext;
use think\response\Json;

class ArticleCateController extends AbstractArticleCrudController
{
    protected const CRUD_VALIDATE = ArticleCateValidate::class;

    public function all(): Json
    {
        $this->resolveCrudContext();
        return $this->data($this->articles->allCategories());
    }

    protected function performLists(TenantContext $context, array $params): PageResult
    {
        return $this->articles->categoryLists($params);
    }

    protected function performDetail(TenantContext $context, array $params): array
    {
        return $this->articles->categoryDetail((int) $params['id']);
    }

    protected function performAdd(TenantContext $context, array $params): bool
    {
        $this->articles->addCategory($params);
        return true;
    }

    protected function performEdit(TenantContext $context, array $params): bool
    {
        $this->articles->editCategory($params);
        return true;
    }

    protected function performDelete(TenantContext $context, array $params): bool
    {
        $this->articles->deleteCategory((int) $params['id']);
        return true;
    }

    protected function performStatusUpdate(TenantContext $context, array $params): bool
    {
        $this->articles->updateCategoryStatus((int) $params['id'], (int) $params['is_show']);
        return true;
    }
}
