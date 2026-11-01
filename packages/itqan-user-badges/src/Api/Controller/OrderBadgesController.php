<?php

namespace Itqan\UserBadges\Api\Controller;

use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Itqan\UserBadges\Badge\Badge;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Persists the badge order from the admin drag-and-drop.
 *
 * Body: `{ order: [{ id: <categoryId|null>, children: [badgeId, …] }, …] }`.
 * Returns the badges in their new order so the store can be updated without a
 * reload (the 1.8 controller did the same).
 */
class OrderBadgesController
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $body = (array) $request->getParsedBody();

        foreach (Arr::get($body, 'order', []) as $category) {
            $categoryId = Arr::get($category, 'id');

            foreach (Arr::get($category, 'children', []) as $position => $badgeId) {
                Badge::query()->where('id', $badgeId)->update([
                    'order' => $position,
                    'badge_category_id' => $categoryId ?: null,
                ]);
            }
        }

        $badges = Badge::query()->orderBy('order')->get();

        return new JsonResponse([
            'data' => $badges->map(fn (Badge $badge) => [
                'type' => 'badges',
                'id' => (string) $badge->id,
                'attributes' => [
                    'order' => (int) $badge->order,
                ],
                'relationships' => [
                    'category' => [
                        'data' => $badge->badge_category_id
                            ? ['type' => 'badge-categories', 'id' => (string) $badge->badge_category_id]
                            : null,
                    ],
                ],
            ])->all(),
        ]);
    }
}
