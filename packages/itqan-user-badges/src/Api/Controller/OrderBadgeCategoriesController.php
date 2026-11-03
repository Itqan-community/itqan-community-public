<?php

namespace Itqan\UserBadges\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\UserBadges\BadgeCategory\BadgeCategory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Persists the category order from the admin "move up / move down" controls.
 *
 * Body: `{ order: [categoryId, …] }`. The client pushes the response into the
 * store, so the new `order` values are returned.
 */
class OrderBadgeCategoriesController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = (array) $request->getParsedBody();

        foreach (array_values((array) Arr::get($body, 'order', [])) as $position => $categoryId) {
            BadgeCategory::query()->where('id', $categoryId)->update(['order' => $position]);
        }

        $categories = BadgeCategory::query()->orderBy('order')->get();

        return new JsonResponse([
            'data' => $categories->map(fn (BadgeCategory $category) => [
                'type' => 'badge-categories',
                'id' => (string) $category->id,
                'attributes' => [
                    'name' => $category->name,
                    'description' => $category->description,
                    'order' => (int) $category->order,
                    'isEnabled' => (bool) $category->is_enabled,
                    'isTable' => (bool) $category->is_table,
                ],
            ])->all(),
        ]);
    }
}
