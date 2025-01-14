<?php

namespace Redmine\Api;

use Redmine\Exception;
use Redmine\Exception\MissingParameterException;
use Redmine\Exception\SerializerException;
use Redmine\Exception\UnexpectedResponseException;
use Redmine\Serializer\PathSerializer;
use Redmine\Serializer\XmlSerializer;

/**
 * Listing users, creating, editing.
 *
 * @see   http://www.redmine.org/projects/redmine/wiki/Rest_Users
 *
 * @author Kevin Saliou <kevin at saliou dot name>
 */
class User extends AbstractApi
{
    private $users = [];

    /**
     * List users.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#GET
     *
     * @param array $params to allow offset/limit (and more) to be passed
     *
     * @throws UnexpectedResponseException if response body could not be converted into array
     *
     * @return array list of users found
     */
    final public function list(array $params = []): array
    {
        try {
            return $this->retrieveData('/users.json', $params);
        } catch (SerializerException $th) {
            throw UnexpectedResponseException::create($this->getLastResponse(), $th);
        }
    }

    /**
     * List users.
     *
     * @deprecated since v2.4.0, use list() instead.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#GET
     *
     * @param array $params to allow offset/limit (and more) to be passed
     *
     * @return array|string|false list of users found or error message or false
     */
    public function all(array $params = [])
    {
        @trigger_error('`' . __METHOD__ . '()` is deprecated since v2.4.0, use `' . __CLASS__ . '::list()` instead.', E_USER_DEPRECATED);

        try {
            $this->users = $this->list($params);
        } catch (Exception $e) {
            if ($this->getLastResponse()->getContent() === '') {
                return false;
            }

            if ($e instanceof UnexpectedResponseException && $e->getPrevious() !== null) {
                $e = $e->getPrevious();
            }

            return $e->getMessage();
        }

        return $this->users;
    }

    /**
     * Returns an array of users with login/id pairs.
     *
     * @param bool  $forceUpdate to force the update of the users var
     * @param array $params      to allow offset/limit (and more) to be passed
     *
     * @return array list of users (id => username)
     */
    public function listing($forceUpdate = false, array $params = [])
    {
        if (empty($this->users) || $forceUpdate) {
            $this->users = $this->list($params);
        }
        $ret = [];
        if (is_array($this->users) && isset($this->users['users'])) {
            foreach ($this->users['users'] as $e) {
                $ret[$e['login']] = (int) $e['id'];
            }
        }

        return $ret;
    }

    /**
     * Return the current user data.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#usersidformat
     *
     * @param array $params extra associated data
     *
     * @return array current user data
     */
    public function getCurrentUser(array $params = [])
    {
        return $this->show('current', $params);
    }

    /**
     * Get a user id given its username.
     *
     * @param string $username
     * @param array  $params   to allow offset/limit (and more) to be passed
     *
     * @return int|bool
     */
    public function getIdByUsername($username, array $params = [])
    {
        $arr = $this->listing(false, $params);
        if (!isset($arr[$username])) {
            return false;
        }

        return $arr[(string) $username];
    }

    /**
     * Get extended information about a user (including memberships + groups).
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#GET-2
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#usersidformat
     * available $params :
     * include: fetch associated data (optional). Possible values:
     *  - memberships: adds extra information about user's memberships and roles on the projects
     *  - groups (added in 2.1): adds extra information about user's groups
     *  - api_key: the API key of the user, visible for admins and for yourself (added in 2.3.0)
     *  - status: a numeric id representing the status of the user, visible for admins only (added in 2.4.0)
     *
     * @param int|string $id     the user id or `current` for retrieving the user whose credentials are used
     * @param array      $params extra associated data
     *
     * @return array information about the user
     */
    public function show($id, array $params = [])
    {
        // set default ones
        $params['include'] = array_unique(
            array_merge(
                isset($params['include']) ? $params['include'] : [],
                [
                    'memberships',
                    'groups',
                ]
            )
        );
        $params['include'] = implode(',', $params['include']);

        return $this->get(
            PathSerializer::create('/users/' . urlencode(strval($id)) . '.json', $params)->getPath()
        );
    }

    /**
     * Create a new user given an array of $params.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#POST
     *
     * @param array $params the new user data
     *
     * @throws MissingParameterException Missing mandatory parameters
     *
     * @return string|false
     */
    public function create(array $params = [])
    {
        $defaults = [
            'login' => null,
            'password' => null,
            'lastname' => null,
            'firstname' => null,
            'mail' => null,
        ];
        $params = $this->sanitizeParams($defaults, $params);

        if (
            !isset($params['login'])
         || !isset($params['lastname'])
         || !isset($params['firstname'])
         || !isset($params['mail'])
        ) {
            throw new MissingParameterException('Theses parameters are mandatory: `login`, `lastname`, `firstname`, `mail`');
        }

        return $this->post(
            '/users.xml',
            XmlSerializer::createFromArray(['user' => $params])->getEncoded()
        );
    }

    /**
     * Update user's information.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#PUT
     *
     * @param int $id the user id
     *
     * @return string|false
     */
    public function update($id, array $params)
    {
        $defaults = [
            'id' => $id,
            'login' => null,
            'password' => null,
            'lastname' => null,
            'firstname' => null,
            'mail' => null,
        ];
        $params = $this->sanitizeParams($defaults, $params);

        return $this->put(
            '/users/' . urlencode(strval($id)) . '.xml',
            XmlSerializer::createFromArray(['user' => $params])->getEncoded()
        );
    }

    /**
     * Delete a user.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Users#DELETE
     *
     * @param int $id id of the user
     *
     * @return false|\SimpleXMLElement|string
     */
    public function remove($id)
    {
        return $this->delete('/users/' . $id . '.xml');
    }
}
