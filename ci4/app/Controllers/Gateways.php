<?php namespace App\Controllers;

use App\Core\ResourceController;
use App\Entities\Gateway;
use App\Libraries\GatewaySteps\GatewayStep;
use App\Libraries\Kubernetes\KubeHelper;
use App\Models\GatewayModel;
use DebugTool\Data;

class Gateways extends ResourceController {

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
        if (!$gateway) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            Data::set('resource', [
                'value' => $step->getPreview($gateway)
            ]);
        } catch (\Exception $e) {
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
     */
    public function deploy(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            $step->deploy($gateway);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->_setResource($gateway);
        $this->success();
    }

    /**
     * @route /gateways/{id}/terminate
     * @method put
     * @custom true
     * @param int $id
     */
    public function terminate(int $id): void {
        /** @var Gateway $gateway */
        $gateway = (new GatewayModel())->find($id);
        if (!$gateway) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        try {
            $step->terminate($gateway);
        } catch (\Exception $e) {
            $this->fail(KubeHelper::PrintException($e));
            return;
        }

        $this->_setResource($gateway);
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
        if (!$gateway) {
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
        if (!$gateway) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        Data::set('resource', ['value' => $step->getKubernetesEvents($gateway)]);
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
        if (!$gateway) {
            $this->fail('unknown gateway');
            return;
        }

        $step = new GatewayStep();
        Data::set('resource', ['value' => $step->getKubernetesStatus($gateway)]);
        $this->success();
    }

}
