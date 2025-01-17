<?php

namespace Redmine\Api;

use InvalidArgumentException;
use Redmine\Exception;
use Redmine\Exception\MissingParameterException;
use Redmine\Exception\SerializerException;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Serializer\PathSerializer;
use Redmine\Serializer\XmlSerializer;
use SimpleXMLElement;

/**
 * Listing projects, creating, editing.
 *
 * @see   http://www.redmine.org/projects/redmine/wiki/Rest_Projects
 *
 * @author Kevin Saliou <kevin at saliou dot name>
 */
class Project extends AbstractApi
{
    private $projects = [];

    /**
     * List projects.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects
     *
     * @param array $params optional parameters to be passed to the api (offset, limit, ...)
     *
     * @throws UnexpectedResponseException if response body could not be converted into array
     *
     * @return array list of projects found
     */
    final public function list(array $params = []): array
    {
        try {
            return $this->retrieveData('/projects.json', $params);
        } catch (SerializerException $th) {
            throw UnexpectedResponseException::create($this->getLastResponse(), $th);
        }
    }

    /**
     * List projects.
     *
     * @deprecated since v2.4.0, use list() instead.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects
     *
     * @param array $params optional parameters to be passed to the api (offset, limit, ...)
     *
     * @return array|string|false list of projects found or error message or false
     */
    public function all(array $params = [])
    {
        @trigger_error('`' . __METHOD__ . '()` is deprecated since v2.4.0, use `' . __CLASS__ . '::list()` instead.', E_USER_DEPRECATED);

        try {
            $this->projects = $this->list($params);
        } catch (Exception $e) {
            if ($this->getLastResponse()->getContent() === '') {
                return false;
            }

            if ($e instanceof UnexpectedResponseException && $e->getPrevious() !== null) {
                $e = $e->getPrevious();
            }

            return $e->getMessage();
        }

        return $this->projects;
    }

    /**
     * Returns an array of projects with name/id pairs (or id/name if $reserse is false).
     *
     * @param bool  $forceUpdate to force the update of the projects var
     * @param bool  $reverse     to return an array indexed by name rather than id
     * @param array $params      to allow offset/limit (and more) to be passed
     *
     * @return array list of projects (id => project name)
     */
    public function listing($forceUpdate = false, $reverse = true, array $params = [])
    {
        if (true === $forceUpdate || empty($this->projects)) {
            $this->projects = $this->list($params);
        }
        $ret = [];
        foreach ($this->projects['projects'] as $e) {
            $ret[(int) $e['id']] = $e['name'];
        }

        return $reverse ? array_flip($ret) : $ret;
    }

    /**
     * Get a project id given its name.
     *
     * @param string $name
     * @param array  $params to allow offset/limit (and more) to be passed
     *
     * @return int|bool
     */
    public function getIdByName($name, array $params = [])
    {
        $arr = $this->listing(false, true, $params);
        if (!isset($arr[$name])) {
            return false;
        }

        return $arr[(string) $name];
    }

    /**
     * Get extended information about a project (including memberships + groups).
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects#Showing-a-project
     *
     * @param string|int $id     project id or identifier
     * @param array      $params available parameters:
     *                           include: fetch associated data (optional). Possible values: trackers, issue_categories, enabled_modules (since 2.6.0)
     *
     * @return array information about the project
     */
    public function show($id, array $params = [])
    {
        if (isset($params['include']) && is_array($params['include'])) {
            $params['include'] = implode(',', $params['include']);
        } else {
            $params['include'] = 'trackers,issue_categories,attachments,relations';
        }

        return $this->get(
            PathSerializer::create('/projects/' . urlencode(strval($id)) . '.json', $params)->getPath()
        );
    }

    /**
     * Create a new project given an array of $params.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects
     *
     * @param array $params the new project data
     *
     * @throws MissingParameterException
     *
     * @return string|SimpleXMLElement|false
     */
    public function create(array $params = [])
    {
        $defaults = [
            'name' => null,
            'identifier' => null,
            'description' => null,
        ];
        $params = $this->sanitizeParams($defaults, $params);

        if (
            !isset($params['name'])
         || !isset($params['identifier'])
        ) {
            throw new MissingParameterException('Theses parameters are mandatory: `name`, `identifier`');
        }

        return $this->post(
            '/projects.xml',
            XmlSerializer::createFromArray(['project' => $params])->getEncoded()
        );
    }

    /**
     * Update project's information.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects
     *
     * @param string|int $id project id or identifier
     *
     * @return string|false
     */
    public function update($id, array $params)
    {
        $defaults = [
            'id' => $id,
            'name' => null,
            'identifier' => null,
            'description' => null,
        ];
        $params = $this->sanitizeParams($defaults, $params);

        return $this->put(
            '/projects/' . urlencode(strval($id)) . '.xml',
            XmlSerializer::createFromArray(['project' => $params])->getEncoded()
        );
    }

    /**
     * Close a project.
     *
     * @see https://www.redmine.org/issues/35507
     *
     * @param string|int $projectIdentifier project id or identifier
     *
     * @throws InvalidArgumentException if $projectIdentifier is not provided as int or string
     * @throws UnexpectedResponseException if the Redmine server delivers an unexpected response
     *
     * @return true if the request was successful
     */
    final public function close($projectIdentifier): bool
    {
        if (! is_int($projectIdentifier) && ! is_string($projectIdentifier)) {
            throw new InvalidArgumentException(sprintf(
                '%s(): Argument #1 ($projectIdentifier) must be of type int or string',
                __METHOD__
            ));
        }

        $this->put(
            '/projects/' . strval($projectIdentifier) . '/close.xml',
            ''
        );

        $lastResponse = $this->getLastResponse();

        if ($lastResponse->getStatusCode() !== 204) {
            throw UnexpectedResponseException::create($lastResponse);
        }

        return true;
    }

    /**
     * Reopen a project.
     *
     * @see https://www.redmine.org/issues/35507
     *
     * @param string|int $projectIdentifier project id or identifier
     *
     * @throws InvalidArgumentException if $projectIdentifier is not provided as int or string
     * @throws UnexpectedResponseException if the Redmine server delivers an unexpected response
     *
     * @return true if the request was successful
     */
    final public function reopen($projectIdentifier): bool
    {
        if (! is_int($projectIdentifier) && ! is_string($projectIdentifier)) {
            throw new InvalidArgumentException(sprintf(
                '%s(): Argument #1 ($projectIdentifier) must be of type int or string',
                __METHOD__
            ));
        }

        $this->put(
            '/projects/' . strval($projectIdentifier) . '/reopen.xml',
            ''
        );

        $lastResponse = $this->getLastResponse();

        if ($lastResponse->getStatusCode() !== 204) {
            throw UnexpectedResponseException::create($lastResponse);
        }

        return true;
    }

    /**
     * Archive a project.
     *
     * @see https://www.redmine.org/issues/35420
     *
     * @param string|int $projectIdentifier project id or identifier
     *
     * @throws InvalidArgumentException if $projectIdentifier is not provided as int or string
     * @throws UnexpectedResponseException if the Redmine server delivers an unexpected response
     *
     * @return true if the request was successful
     */
    final public function archive($projectIdentifier): bool
    {
        if (! is_int($projectIdentifier) && ! is_string($projectIdentifier)) {
            throw new InvalidArgumentException(sprintf(
                '%s(): Argument #1 ($projectIdentifier) must be of type int or string',
                __METHOD__
            ));
        }

        $this->put(
            '/projects/' . strval($projectIdentifier) . '/archive.xml',
            ''
        );

        $lastResponse = $this->getLastResponse();

        if ($lastResponse->getStatusCode() !== 204) {
            throw UnexpectedResponseException::create($lastResponse);
        }

        return true;
    }

    /**
     * Unarchive a project.
     *
     * @see https://www.redmine.org/issues/35420
     *
     * @param string|int $projectIdentifier project id or identifier
     *
     * @throws InvalidArgumentException if $projectIdentifier is not provided as int or string
     * @throws UnexpectedResponseException if the Redmine server delivers an unexpected response
     *
     * @return true if the request was successful
     */
    final public function unarchive($projectIdentifier): bool
    {
        if (! is_int($projectIdentifier) && ! is_string($projectIdentifier)) {
            throw new InvalidArgumentException(sprintf(
                '%s(): Argument #1 ($projectIdentifier) must be of type int or string',
                __METHOD__
            ));
        }

        $this->put(
            '/projects/' . strval($projectIdentifier) . '/unarchive.xml',
            ''
        );

        $lastResponse = $this->getLastResponse();

        if ($lastResponse->getStatusCode() !== 204) {
            throw UnexpectedResponseException::create($lastResponse);
        }

        return true;
    }

    /**
     * @deprecated since v2.3.0, use `\Redmine\Serializer\XmlSerializer::createFromArray()` instead.
     *
     * @param array $params
     *
     * @return \SimpleXMLElement
     */
    protected function prepareParamsXml($params)
    {
        @trigger_error('`' . __METHOD__ . '()` is deprecated since v2.3.0, use `\Redmine\Serializer\XmlSerializer::createFromArray()` instead.', E_USER_DEPRECATED);

        return new \SimpleXMLElement(
            XmlSerializer::createFromArray(['project' => $params])->getEncoded()
        );
    }

    /**
     * Delete a project.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Projects
     *
     * @param int $id id of the project
     *
     * @return false|\SimpleXMLElement|string
     */
    public function remove($id)
    {
        return $this->delete('/projects/' . $id . '.xml');
    }
}
