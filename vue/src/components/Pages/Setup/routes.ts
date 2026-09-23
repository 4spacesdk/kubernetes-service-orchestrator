import SystemRoutes from '@/components/Pages/Setup/System/routes';
import DomainRoutes from '@/components/Pages/Setup/Domains/routes';
import DatabaseServices from '@/components/Pages/Setup/DatabaseServices/routes';
import EmailServices from '@/components/Pages/Setup/EmailServices/routes';
import Projects from '@/components/Pages/Setup/Projects/routes';
import Deployments from '@/components/Pages/Setup/Deployments/routes';
import ContainerImages from '@/components/Pages/Setup/ContainerImages/routes';
import DeploymentSpecifications from '@/components/Pages/Setup/DeploymentSpecifications/routes';
import WorkspaceTemplates from '@/components/Pages/Setup/WorkspaceTemplates/routes';
import GatewayRoutes from '@/components/Pages/Setup/Gateways/routes';

export default ([
    ...SystemRoutes,
    ...Projects,
    ...DomainRoutes,
    ...DatabaseServices,
    ...EmailServices,
    ...Deployments,
    ...ContainerImages,
    ...DeploymentSpecifications,
    ...WorkspaceTemplates,
    ...GatewayRoutes,
]);

