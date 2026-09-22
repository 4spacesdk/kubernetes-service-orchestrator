<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Gateway;
use App\Entities\GatewayAddress;
use App\Entities\GatewayAnnotation;
use App\Interfaces\GatewayAddressList;
use App\Interfaces\GatewayAnnotationList;
use App\Libraries\Audit\Audit;
use App\Libraries\GatewaySteps\GatewayStep;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\GatewayModel;
use DebugTool\Data;

class Gateways extends ResourceController {

    /**
     * No blanket replace. The generic `put()` writes every column of the row, and the ones a
     * request leaves out become null - on this resource that is the gateway's name, class and namespace. kso updates
     * with PATCH everywhere, and nothing ever called this; it existed only because the route
     * generator finds the inherited method.
     *
     * @ignore true
     * @param $id
     * @return void
     */
    public function put($id = 0) {
    }

    /**
     * @route /gateways/{id}/preview
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema StringInterface
     */
    public function getPreview(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            Data::set('resource', [
                'value' => $step->getPreview($gateway)
            ]);
        } catch (\Throwable $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /gateways/{id}/deploy
     * @method put
     * @custom true
     * @param int $id
     * @audit gateway.deploy
     */
    public function deploy(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            $step->deploy($gateway);
        } catch (\Throwable $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->_setResource($gateway);
        Audit::Record('gateway.deploy', $gateway);
        $this->success();
    }

    /**
     * @route /gateways/{id}/terminate
     * @method put
     * @custom true
     * @param int $id
     * @audit gateway.terminate
     */
    public function terminate(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            $step->terminate($gateway);
        } catch (\Throwable $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->_setResource($gateway);
        Audit::Record('gateway.terminate', $gateway);
        $this->success();
    }

    /**
     * @route /gateways/{id}/status
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema StringInterface
     */
    public function getStatus(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        Data::set('resource', [
            'value' => $step->getStatus($gateway)
        ]);
        $this->success();
    }

    /**
     * @route /gateways/{id}/kubernetes-events
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema StringInterface
     */
    public function getKubernetesEvents(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            Data::set('resource', ['value' => $step->getKubernetesEvents($gateway)]);
        } catch (\Throwable $e) {
            // As the four endpoints above: a cluster that cannot be reached is a message on
            // the gateway page, not an exception out of the controller.
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /gateways/{id}/kubernetes-status
     * @method get
     * @custom true
     * @param int $id
     * @responseSchema StringInterface
     */
    public function getKubernetesStatus(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            Data::set('resource', ['value' => $step->getKubernetesStatus($gateway)]);
        } catch (\Throwable $e) {
            // As the four endpoints above: a cluster that cannot be reached is a message on
            // the gateway page, not an exception out of the controller.
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->success();
    }

    /**
     * @route /gateways/{id}/gateway-addresses
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema GatewayAddressList
     * @return void
     * @audit entity
     */
    public function updateGatewayAddresses(int $id): void {
        $item = new Gateway();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        /** @var GatewayAddressList $body */
        $body = $this->request->getJSON();

        $addresses = [];
        foreach ($body->values as $data) {
            $address = new GatewayAddress();
            $address->type = $data->type;
            $address->value = $data->value;
            if ($error = $address->validate()) {
                $this->fail($error);
                return;
            }
            $addresses[] = $address;
        }

        $values = new GatewayAddress();
        foreach ($addresses as $address) {
            $address->save();
        }
        $values->all = $addresses;

        $item->updateGatewayAddresses($values);
        $this->_setResource($item);
        $this->success();
    }

    /**
     * Replace the gateway's annotations with the ones sent (#65). They reach the cluster on
     * the next deploy. Nothing is written unless every one of them is valid.
     *
     * @route /gateways/{id}/gateway-annotations
     * @method put
     * @custom true
     * @param int $id
     * @requestSchema GatewayAnnotationList
     * @return void
     * @audit entity
     */
    public function updateGatewayAnnotations(int $id): void {
        $item = new Gateway();
        $item->find($id);
        if (!$item->exists()) {
            $this->fail('unknown gateway');
            return;
        }

        /** @var GatewayAnnotationList $body */
        $body = $this->request->getJSON();

        $annotations = [];
        foreach ($body->values ?? [] as $data) {
            $annotation = new GatewayAnnotation();
            $annotation->name = trim((string) ($data->name ?? ''));
            $annotation->value = (string) ($data->value ?? '');
            if ($error = $annotation->validate()) {
                $this->fail($error);
                return;
            }
            // A resource has one value per key; the second would silently win.
            if (isset($annotations[$annotation->name])) {
                $this->fail("'{$annotation->name}' is there twice");
                return;
            }
            $annotations[$annotation->name] = $annotation;
        }

        $values = new GatewayAnnotation();
        foreach ($annotations as $annotation) {
            $annotation->save();
        }
        $values->all = array_values($annotations);

        $item->updateGatewayAnnotations($values);
        $this->_setResource($item);
        $this->success();
    }

}
