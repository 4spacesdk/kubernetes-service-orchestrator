import OAuthClientsRoutes from '@/components/Pages/Integrations/OAuthClients/routes';
import WebhooksRoutes from '@/components/Pages/Integrations/Webhooks/routes';

export default ([
    ...OAuthClientsRoutes,
    ...WebhooksRoutes,
]);

