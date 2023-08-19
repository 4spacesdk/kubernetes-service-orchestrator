<?php namespace App\Libraries\DeploymentSteps\Helpers;

class DeploymentSteps {

    const
        Database = 'database',
        Namespace = 'namespace',
        ClusterRole = 'cluster-role',
        ServiceAccount = 'service-account',
        ClusterRoleBinding = 'cluster-role-binding',
        PersistentVolume = 'persistent-volume',
        PersistentVolumeClaim = 'persistent-volume-claim',
        Cronjob = 'cronjob',
        Deployment = 'deployment',
        Service = 'service',
        Ingress = 'ingress',
        Redirects = 'redirects',
        Migration = 'migration';

}
