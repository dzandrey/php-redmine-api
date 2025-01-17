<?php

namespace Redmine\Api;

use Redmine\Client\NativeCurlClient;
use Redmine\Client\Psr18Client;
use Redmine\Exception;
use Redmine\Exception\SerializerException;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Serializer\JsonSerializer;
use Redmine\Serializer\PathSerializer;
use Redmine\Serializer\XmlSerializer;
use SimpleXMLElement;

/**
 * Listing issues, searching, editing and closing your projects issues.
 *
 * @see   http://www.redmine.org/projects/redmine/wiki/Rest_Issues
 *
 * @author Kevin Saliou <kevin at saliou dot name>
 */
class Issue extends AbstractApi
{
    public const PRIO_LOW = 1;
    public const PRIO_NORMAL = 2;
    public const PRIO_HIGH = 3;
    public const PRIO_URGENT = 4;
    public const PRIO_IMMEDIATE = 5;

    /**
     * @var IssueCategory
     */
    private $issueCategoryApi;

    /**
     * @var IssueStatus
     */
    private $issueStatusApi;

    /**
     * @var Project
     */
    private $projectApi;

    /**
     * @var Tracker
     */
    private $trackerApi;

    /**
     * @var User
     */
    private $userApi;

    /**
     * List issues.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues
     * available $params :
     * - offset: skip this number of issues in response (optional)
     * - limit: number of issues per page (optional)
     * - sort: column to sort with. Append :desc to invert the order.
     * - project_id: get issues from the project with the given id, where id is either project id or project identifier
     * - tracker_id: get issues from the tracker with the given id
     * - status_id: get issues with the given status id only. Possible values: open, closed, * to get open and closed issues, status id
     * - assigned_to_id: get issues which are assigned to the given user id
     * - cf_x: get issues with the given value for custom field with an ID of x. (Custom field must have 'used as a filter' checked.)
     * - query_id : id of the previously saved query
     *
     * @param array $params the additional parameters (cf available $params above)
     *
     * @throws UnexpectedResponseException if response body could not be converted into array
     *
     * @return array list of issues found
     */
    final public function list(array $params = []): array
    {
        try {
            return $this->retrieveData('/issues.json', $params);
        } catch (SerializerException $th) {
            throw UnexpectedResponseException::create($this->getLastResponse(), $th);
        }
    }

    /**
     * List issues.
     *
     * @deprecated since v2.4.0, use list() instead.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues
     * available $params :
     * - offset: skip this number of issues in response (optional)
     * - limit: number of issues per page (optional)
     * - sort: column to sort with. Append :desc to invert the order.
     * - project_id: get issues from the project with the given id, where id is either project id or project identifier
     * - tracker_id: get issues from the tracker with the given id
     * - status_id: get issues with the given status id only. Possible values: open, closed, * to get open and closed issues, status id
     * - assigned_to_id: get issues which are assigned to the given user id
     * - cf_x: get issues with the given value for custom field with an ID of x. (Custom field must have 'used as a filter' checked.)
     * - query_id : id of the previously saved query
     *
     * @param array $params the additional parameters (cf available $params above)
     *
     * @return array|string|false list of issues found or error message or false
     */
    public function all(array $params = [])
    {
        @trigger_error('`' . __METHOD__ . '()` is deprecated since v2.4.0, use `' . __CLASS__ . '::list()` instead.', E_USER_DEPRECATED);

        try {
            return $this->list($params);
        } catch (Exception $e) {
            if ($this->getLastResponse()->getContent() === '') {
                return false;
            }

            if ($e instanceof UnexpectedResponseException && $e->getPrevious() !== null) {
                $e = $e->getPrevious();
            }

            return $e->getMessage();
        }
    }

    /**
     * Get extended information about an issue gitven its id.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues#Using-JSON
     * available $params :
     * include: fetch associated data (optional). Possible values: children, attachments, relations, changesets and journals
     *
     * @param int    $id     the issue id
     * @param array  $params extra associated data
     *
     * @return array information about the issue
     */
    public function show($id, array $params = [])
    {
        if (isset($params['include']) && is_array($params['include'])) {
            $params['include'] = implode(',', $params['include']);
        }

        return $this->get(
            PathSerializer::create('/issues/' . urlencode(strval($id)) . '.json', $params)->getPath()
        );
    }

    /**
     * Create a new issue given an array of $params
     * The issue is assigned to the authenticated user.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues#Creating-an-issue
     *
     * @param array $params the new issue data
     *
     * @return string|SimpleXMLElement|false
     */
    public function create(array $params = [])
    {
        $defaults = [
            'subject' => null,
            'description' => null,
            'project_id' => null,
            'category_id' => null,
            'priority_id' => null,
            'status_id' => null,
            'tracker_id' => null,
            'assigned_to_id' => null,
            'author_id' => null,
            'due_date' => null,
            'start_date' => null,
            'watcher_user_ids' => null,
            'fixed_version_id' => null,
        ];
        $params = $this->cleanParams($params);
        $params = $this->sanitizeParams($defaults, $params);

        return $this->post(
            '/issues.xml',
            XmlSerializer::createFromArray(['issue' => $params])->getEncoded()
        );
    }

    /**
     * Update issue information's by username, repo and issue number. Requires authentication.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues#Updating-an-issue
     *
     * @param int $id the issue number
     *
     * @return string|SimpleXMLElement|false
     */
    public function update($id, array $params)
    {
        $defaults = [
            'id' => $id,
            'subject' => null,
            'notes' => null,
            'private_notes' => false,
            'category_id' => null,
            'priority_id' => null,
            'status_id' => null,
            'tracker_id' => null,
            'assigned_to_id' => null,
            'due_date' => null,
        ];
        $params = $this->cleanParams($params);
        $sanitizedParams = $this->sanitizeParams($defaults, $params);

        // Allow assigned_to_id to be `` (empty string) to unassign a user from an issue
        if (array_key_exists('assigned_to_id', $params) && '' === $params['assigned_to_id']) {
            $sanitizedParams['assigned_to_id'] = '';
        }

        return $this->put(
            '/issues/' . urlencode(strval($id)) . '.xml',
            XmlSerializer::createFromArray(['issue' => $sanitizedParams])->getEncoded()
        );
    }

    /**
     * @param int $id
     * @param int $watcherUserId
     *
     * @return false|string
     */
    public function addWatcher($id, $watcherUserId)
    {
        return $this->post(
            '/issues/' . urlencode(strval($id)) . '/watchers.xml',
            XmlSerializer::createFromArray(['user_id' => urlencode(strval($watcherUserId))])->getEncoded()
        );
    }

    /**
     * @param int $id
     * @param int $watcherUserId
     *
     * @return false|\SimpleXMLElement|string
     */
    public function removeWatcher($id, $watcherUserId)
    {
        return $this->delete('/issues/' . urlencode(strval($id)) . '/watchers/' . urlencode(strval($watcherUserId)) . '.xml');
    }

    /**
     * @param int    $id
     * @param string $status
     *
     * @return string|SimpleXMLElement|false
     */
    public function setIssueStatus($id, $status)
    {
        $issueStatusApi = $this->getIssueStatusApi();

        return $this->update($id, [
            'status_id' => $issueStatusApi->getIdByName($status),
        ]);
    }

    /**
     * @param int    $id
     * @param string $note
     * @param bool   $privateNote
     *
     * @return string|false
     */
    public function addNoteToIssue($id, $note, $privateNote = false)
    {
        return $this->update($id, [
            'notes' => $note,
            'private_notes' => $privateNote,
        ]);
    }

    /**
     * Transforms literal identifiers to integer ids.
     *
     * @return array
     */
    private function cleanParams(array $params = [])
    {
        if (isset($params['project'])) {
            $projectApi = $this->getProjectApi();

            $params['project_id'] = $projectApi->getIdByName($params['project']);
            unset($params['project']);
        }

        if (isset($params['category']) && isset($params['project_id'])) {
            $issueCategoryApi = $this->getIssueCategoryApi();

            $params['category_id'] = $issueCategoryApi->getIdByName($params['project_id'], $params['category']);
            unset($params['category']);
        }

        if (isset($params['status'])) {
            $issueStatusApi = $this->getIssueStatusApi();

            $params['status_id'] = $issueStatusApi->getIdByName($params['status']);
            unset($params['status']);
        }

        if (isset($params['tracker'])) {
            $trackerApi = $this->getTrackerApi();

            $params['tracker_id'] = $trackerApi->getIdByName($params['tracker']);
            unset($params['tracker']);
        }

        if (isset($params['assigned_to'])) {
            $userApi = $this->getUserApi();

            $params['assigned_to_id'] = $userApi->getIdByUsername($params['assigned_to']);
            unset($params['assigned_to']);
        }

        if (isset($params['author'])) {
            $userApi = $this->getUserApi();

            $params['author_id'] = $userApi->getIdByUsername($params['author']);
            unset($params['author']);
        }

        return $params;
    }

    /**
     * Attach a file to an issue. Requires authentication.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues#Updating-an-issue
     *
     * @param int    $id         the issue number
     * @param array  $attachment ['token' => '...', 'filename' => '...', 'content_type' => '...']
     *
     * @return bool|string
     */
    public function attach($id, array $attachment)
    {
        return $this->attachMany($id, [$attachment]);
    }

    /**
     * Attach files to an issue. Requires authentication.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Issues#Updating-an-issue
     *
     * @param int    $id          the issue number
     * @param array  $attachments [
     *                            ['token' => '...', 'filename' => '...', 'content_type' => '...'],
     *                            ['token' => '...', 'filename' => '...', 'content_type' => '...']
     *                            ]
     *
     * @return bool|string
     */
    public function attachMany($id, array $attachments)
    {
        $params = [
            'id' => $id,
            'uploads' => $attachments,
        ];

        return $this->put(
            '/issues/' . urlencode(strval($id)) . '.json',
            JsonSerializer::createFromArray(['issue' => $params])->getEncoded()
        );
    }

    /**
     * Remove a issue by issue number.
     *
     * @param int $id the issue number
     *
     * @return false|\SimpleXMLElement|string
     */
    public function remove($id)
    {
        return $this->delete('/issues/' . urlencode(strval($id)) . '.xml');
    }

    /**
     * @return IssueCategory
     */
    private function getIssueCategoryApi()
    {
        if ($this->issueCategoryApi === null) {
            if ($this->client !== null && ! $this->client instanceof NativeCurlClient && ! $this->client instanceof Psr18Client) {
                /** @var IssueCategory */
                $issueCategoryApi = $this->client->getApi('issue_category');
            } else {
                $issueCategoryApi = new IssueCategory($this->getHttpClient());
            }

            $this->issueCategoryApi = $issueCategoryApi;
        }

        return $this->issueCategoryApi;
    }

    /**
     * @return IssueStatus
     */
    private function getIssueStatusApi()
    {
        if ($this->issueStatusApi === null) {
            if ($this->client !== null && ! $this->client instanceof NativeCurlClient && ! $this->client instanceof Psr18Client) {
                /** @var IssueStatus */
                $issueStatusApi = $this->client->getApi('issue_status');
            } else {
                $issueStatusApi = new IssueStatus($this->getHttpClient());
            }

            $this->issueStatusApi = $issueStatusApi;
        }

        return $this->issueStatusApi;
    }

    /**
     * @return Project
     */
    private function getProjectApi()
    {
        if ($this->projectApi === null) {
            if ($this->client !== null && ! $this->client instanceof NativeCurlClient && ! $this->client instanceof Psr18Client) {
                /** @var Project */
                $projectApi = $this->client->getApi('project');
            } else {
                $projectApi = new Project($this->getHttpClient());
            }

            $this->projectApi = $projectApi;
        }

        return $this->projectApi;
    }

    /**
     * @return Tracker
     */
    private function getTrackerApi()
    {
        if ($this->trackerApi === null) {
            if ($this->client !== null && ! $this->client instanceof NativeCurlClient && ! $this->client instanceof Psr18Client) {
                /** @var Tracker */
                $trackerApi = $this->client->getApi('tracker');
            } else {
                $trackerApi = new Tracker($this->getHttpClient());
            }

            $this->trackerApi = $trackerApi;
        }

        return $this->trackerApi;
    }

    /**
     * @return User
     */
    private function getUserApi()
    {
        if ($this->userApi === null) {
            if ($this->client !== null && ! $this->client instanceof NativeCurlClient && ! $this->client instanceof Psr18Client) {
                /** @var User */
                $userApi = $this->client->getApi('user');
            } else {
                $userApi = new User($this->getHttpClient());
            }

            $this->userApi = $userApi;
        }

        return $this->userApi;
    }
}
