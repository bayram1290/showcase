<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use App\Helpers\ApiResponse;
use App\Models\User;
use App\Services\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;


class UserController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Fetch a list of users with filters and pagination.
     *
     * @param Request $request The HTTP request containing the filters and pagination.
     *
     * @return JsonResponse The JSON response containing the list of users.
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->userService->list($request);

        return ApiResponse::success(
            $users,
            'Users fetched successfully.',
            200
        );
    }

    /**
     * Update a user.
     *
     * @param Request $request The HTTP request containing the user data.
     * @param User $user The user to be updated.
     *
     * @return JsonResponse The JSON response containing the updated user data.
     *
     * @throws \Exception If the user data is invalid or if the user cannot be updated.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $user_data = $request->validated();
            $user_data = $this->userService->update($user, $user_data);

            return ApiResponse::success($user_data, 'User updated successfully.', 200);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'UPDATE_USER_ERROR',
                500
            );
        }
    }

    /**
     * Soft delete a user.
     *
     * @param Request $request The HTTP request containing the user data.
     * @param User $user The user to be deleted.
     *
     * @return JsonResponse The JSON response containing the result of the deletion.
     *
     * @throws \Exception If the user data is invalid or if the user cannot be deleted.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        try {
            $this->userService->delete($user, $request->user());

            return ApiResponse::success(null, 'User deleted successfully.', 200);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DELETE_USER_ERROR',
                500
            );
        }
    }

    /**
     * Activate a user.
     *
     * @param Request $request The HTTP request containing the user data.
     * @param User $user The user to be activated.
     *
     * @return JsonResponse The JSON response containing the result of the activation.
     *
     * @throws \Exception If the user data is invalid or if the user cannot be activated.
     */
    public function activate(Request $request, User $user): JsonResponse
    {
        try {
            $this->userService->activate($user, $request->user());

            return ApiResponse::success(null, 'User activated successfully.', 200);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'ACTIVATE_USER_ERROR',
                500
            );
        }
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        try {
            $this->userService->deactivate($user, $request->user());

            return ApiResponse::success(null, 'User deactivated successfully.', 200);
        } catch (\Exception $e) {
            return ApiResponse::error(
                $e->getMessage(),
                'DEACTIVATE_USER_ERROR',
                500
            );
        }
    }
}