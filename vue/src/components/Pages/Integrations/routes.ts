import OAuthClientsRoutes from '@/components/Pages/Integrations/OAuthClients/routes';
import WebhooksRoutes from '@/components/Pages/Integrations/Webhooks/routes';
import PodioIntegrationsRoutes from '@/components/Pages/Integrations/PodioIntegrations/routes';
import GithubIntegrationsRoutes from '@/components/Pages/Integrations/GithubIntegrations/routes';
import ContainerRegistriesRoutes from '@/components/Pages/Integrations/ContainerRegistries/routes';

export default ([
    ...OAuthClientsRoutes,
    ...WebhooksRoutes,
    ...PodioIntegrationsRoutes,
    ...GithubIntegrationsRoutes,
    ...ContainerRegistriesRoutes,
]);

