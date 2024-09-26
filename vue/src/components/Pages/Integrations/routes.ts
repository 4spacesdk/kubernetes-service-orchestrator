import OAuthClientsRoutes from '@/components/Pages/Integrations/OAuthClients/routes';
import WebhooksRoutes from '@/components/Pages/Integrations/Webhooks/routes';
import PodioIntegrationsRoutes from '@/components/Pages/Integrations/PodioIntegrations/routes';

export default ([
    ...OAuthClientsRoutes,
    ...WebhooksRoutes,
    ...PodioIntegrationsRoutes,
]);

