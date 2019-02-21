<?php

namespace Blazing\Reseller\Api\Api;

class UserApi extends AbstractApi
{
    public function exportUserData($userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->get('/user/{userId}/export/user-related-data', ['userId' => $userId]);
    }

    public function getDetails($userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->get('/user/{userId}/details', ['userId' => $userId]);
    }

    public function updateSettings(array $data, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);
        return $this->api->request()->post('/user/{userId}/details', [
            'userId'   => $userId,
            'data' => $data
        ]);
    }

    public function updateSneakerLocation($location, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->post('/user/{userId}/details/sneakerLocation', [
            'userId'   => $userId,
            'location' => $location
        ]);
    }

    public function getAuthIpList($userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->get('/user/{userId}/details/auth/ip', ['userId' => $userId]);
    }

    public function addAuthIp($ip, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->post('/user/{userId}/details/auth/ip/{ip}', ['userId' => $userId, 'ip' => $ip]);
    }

    public function deleteAuthIp($ipId, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->delete('/user/{userId}/details/auth/ip/{ipId}', ['userId' => $userId, 'ipId' => $ipId]);
    }

    public function getAnnouncement($userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->get('/user/{userId}/announcement', ['userId' => $userId,'site'=>'hosting']);
    }

    public function postAnnouncementAction($anoncementId, $action, $data = false, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->post('/user/{userId}/announcement/{action}', [
            'anoncementId' => $anoncementId,
            'userId' => $userId,
            'data' => $data,
            'action' => $action,
            'site' => 'hosting'
        ]);
    }

    public function getAnnouncementResponses($announcementId, $action = null, $userId = null)
    {
        $userId or $userId = $this->api->getContext()->getUserId(true);

        return $this->api->request()->get('/user/{userId}/announcement/{id}/responses', [
            'userId' => $userId,
            'id' => $announcementId,
            'action' => $action,
            'site' => "hosting"
        ]);
    }
}