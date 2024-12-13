<?php namespace App\Libraries\DeploymentSteps\Helpers;

class DeploymentSteps {

    const string
        Database = 'database',
        Namespace = 'namespace',
        ClusterRole = 'cluster-role',
        Role = 'role',
        ServiceAccount = 'service-account',
        ClusterRoleBinding = 'cluster-role-binding',
        RoleBinding = 'role-binding',
        PersistentVolume = 'persistent-volume',
        PersistentVolumeClaim = 'persistent-volume-claim',
        Cronjob = 'cronjob',
        CustomResource = 'custom-resource',
        Deployment = 'deployment',
        Service = 'service',
        Ingress = 'ingress',
        Redirects = 'redirects',
        Migration = 'migration';

}
