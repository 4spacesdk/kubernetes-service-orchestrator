import SystemRoutes from '@/components/Pages/Setup/System/routes';
import DomainRoutes from '@/components/Pages/Setup/Domains/routes';
import DatabaseServices from '@/components/Pages/Setup/DatabaseServices/routes';
import EmailServices from '@/components/Pages/Setup/EmailServices/routes';
import Deployments from '@/components/Pages/Setup/Deployments/routes';
import ContainerImages from '@/components/Pages/Setup/ContainerImages/routes';
import DeploymentSpecifications from '@/components/Pages/Setup/DeploymentSpecifications/routes';
import DeploymentPackages from '@/components/Pages/Setup/DeploymentPackages/routes';

export default ([
    ...SystemRoutes,
    ...DomainRoutes,
    ...DatabaseServices,
    ...EmailServices,
    ...Deployments,
    ...ContainerImages,
    ...DeploymentSpecifications,
    ...DeploymentPackages,
]);

