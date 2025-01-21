<?php namespace App\Libraries\DeploymentSteps\Helpers;

class DeploymentSteps {

    // Level: Workspace
    const string
        Namespace = 'namespace',
        ContourHttpProxy = 'contour-http-proxy'
    ;

    // Level: Deployment
    const string
        Database = 'database',
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
        KService = 'kservice',
        Service = 'service',
        Ingress = 'ingress',
        IstioVirtualService = 'istio-virtual-service',
        Migration = 'migration';

}
