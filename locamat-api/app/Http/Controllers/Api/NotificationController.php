<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    public function __construct(private readonly LocationService $locationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $this->genererRappels($user);

        return NotificationResource::collection(
            $user->notifications()->latest()->paginate(15),
        )->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->genererRappels($user);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return new NotificationResource($item->refresh());
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    /**
     * Les rappels de location sont créés ici, quand l'application est ouverte : aucune tâche
     * planifiée n'est nécessaire. Le contrôle est limité à une fois toutes les 5 minutes par
     * utilisateur pour ne pas alourdir les rafraîchissements du compteur.
     */
    private function genererRappels(User $user): void
    {
        if (! Cache::add('rappels-appli:'.$user->getKey(), true, now()->addMinutes(5))) {
            return;
        }

        $this->locationService->genererRappelsPour($user);
    }
}
