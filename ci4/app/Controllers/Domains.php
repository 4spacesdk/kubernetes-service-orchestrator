<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Domain;
use App\Interfaces\DomainsGetCertificateStatusResponse;
use App\Libraries\Audit\Audit;
use App\Libraries\Kubernetes\CustomResourceDefinitions\K8sIstioGateway;
use App\Libraries\Kubernetes\KubeAuth;
use App\Libraries\Kubernetes\KubeCertificate;
use App\Libraries\Kubernetes\KubeIstioGateway;
use DebugTool\Data;
use RenokiCo\PhpK8s\Kinds\K8sEvent;

class Domains extends ResourceController {

    /**
     * @route /domains/{id}/certificate/apply
     * @method put
     * @custom true
     * @param int $id
     * @return void
     * @throws \Exception
     * @audit domain.apply_certificate
     */
    public function applyCertificate(int $id = 0): void {
        $item = new Domain();
        $item->find($id);
        if ($item->exists()) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $certificate = new KubeCertificate($item);
            $applied = $certificate->apply($cluster);
            if (is_string($applied)) {
                $this->fail($applied);
                return;
            }
        }
        if ($item->exists()) {
            Audit::Record('domain.apply_certificate', $item);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /domains/{id}/certificate/events
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema DomainsGetCertificateEventsResponse
     * @return void
     * @throws \Exception
     */
    public function getCertificateEvents(int $id = 0): void {
        $item = new Domain();
        $item->find($id);

        $events = [];
        if ($item->exists()) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $certificate = new KubeCertificate($item);
            /** @var K8sEvent $event */
            foreach ($certificate->getEvents($cluster) as $event) {
                $events[] = [
                    'type' => $event->getType(),
                    'reason' => $event->getAttribute('reason'),
                    'age' => date('Y-m-d H:i:s', strtotime_($event->getAttribute('lastTimestamp'))),
                    'from' => $event->getAttribute('source')['component'] ?? '',
                    'message' => $event->getMessage(),
                ];
            }
        }
        Data::set('resources', $events);
        $this->success();
    }

    /**
     * @route /domains/{id}/certificate/status
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema DomainsGetCertificateStatusResponse
     * @return void
     * @throws \Exception
     */
    public function getCertificateStatus(int $id = 0): void {
        $item = new Domain();
        $item->find($id);

        /** @var DomainsGetCertificateStatusResponse $data */
        $data = [];
        if ($item->exists()) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $certificate = new KubeCertificate($item);
            $status = $certificate->getStatus($cluster);
            $data['notAfter'] = date('Y-m-d H:i:s', strtotime_($status['notAfter'] ?? ''));
            $data['notBefore'] = date('Y-m-d H:i:s', strtotime_($status['notBefore'] ?? ''));
            $data['renewalTime'] = date('Y-m-d H:i:s', strtotime_($status['renewalTime'] ?? ''));
            $data['conditions'] = [];
            foreach ($status['conditions'] ?? [] as $condition) {
                $data['conditions'][] = [
                    'type' => $condition['type'],
                    'status' => $condition['status'],
                    'reason' => $condition['reason'],
                    'lastTransitionTime' => date('Y-m-d H:i:s', strtotime_($condition['lastTransitionTime'])),
                    'message' => $condition['message'],
                ];
            }
        }
        Data::set('resource', $data);
        $this->success();
    }

    /**
     * @route /domains/{id}/istio-gateway/apply
     * @method put
     * @custom true
     * @param int $id
     * @return void
     * @throws \Exception
     * @audit domain.apply_istio_gateway
     */
    public function applyIstioGateway(int $id = 0): void {
        $item = new Domain();
        $item->find($id);
        if ($item->exists()) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $gateway = new KubeIstioGateway($item);
            $success = $gateway->apply($cluster);
            if (is_string($success)) {
                $this->fail($success);
                return;
            }
        }
        if ($item->exists()) {
            Audit::Record('domain.apply_istio_gateway', $item);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @route /domains/{id}/istio-gateway/terminate
     * @method put
     * @custom true
     * @param int $id
     * @return void
     * @throws \Exception
     * @audit domain.terminate_istio_gateway
     */
    public function terminateIstioGateway(int $id = 0): void {
        $item = new Domain();
        $item->find($id);
        if ($item->exists()) {
            $auth = new KubeAuth();
            $cluster = $auth->authenticate();
            $gateway = new KubeIstioGateway($item);
            $success = $gateway->delete($cluster);
            if (is_string($success)) {
                $this->fail($success);
                return;
            }
        }
        if ($item->exists()) {
            Audit::Record('domain.terminate_istio_gateway', $item);
        }
        $this->_setResource($item);
        $this->success();
    }

    /**
     * @param $id
     * @return void
     * @codeCoverageIgnore
     * @ignore true
     */
    public function put($id = 0) {
    }

}
