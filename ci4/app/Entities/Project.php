<?php namespace App\Entities;

use App\Core\Entity;
use App\Models\ProjectModel;
use App\Models\UserModel;

/**
 * A division of the workspaces - "Customers", "Minor (tst)", "Tools" - with the users it is
 * relevant to. It decides what a user is shown first, not what a user may reach: everybody who
 * can sign in is trusted, and a user can always look at every project.
 *
 * A workspace is in one project, or none. A workspace template names the project its
 * workspaces are made in.
 *
 * @property string $name
 * @property string $description
 *
 * Many
 * @property Workspace $workspaces
 * @property WorkspaceTemplate $workspace_templates
 * @property User $users
 */
class Project extends Entity {

    /**
     * The users joined to the project are these, and only these. One link at a time, as
     * RbacRole::updatePermissions() explains; an id that is no user is left out.
     *
     * @param int[] $userIds
     */
    public function updateUsers(array $userIds): void {
        /** @var User $existing */
        $existing = (new UserModel())->whereRelated(ProjectModel::class, 'id', $this->id)->find();
        foreach ($existing as $user) {
            $this->delete($user);
        }

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $user = new User();
            $user->find($userId);
            if ($user->exists()) {
                $this->save($user);
            }
        }

        $this->users = (new UserModel())->whereRelated(ProjectModel::class, 'id', $this->id)->find();
    }

    /**
     * @return \ArrayIterator|\OrmExtension\Extensions\Entity[]|\Traversable|Project[]
     */
    public function getIterator(): \ArrayIterator {
        return parent::getIterator();
    }

}
