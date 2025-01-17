<?php

namespace Redmine\Api;

use Redmine\Serializer\PathSerializer;

/**
 * Attachment details.
 *
 * @see   http://www.redmine.org/projects/redmine/wiki/Rest_Attachments
 *
 * @author Kevin Saliou <kevin at saliou dot name>
 */
class Attachment extends AbstractApi
{
    /**
     * Get extended information about an attachment.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_Attachments
     *
     * @param int $id the attachment number
     *
     * @return array information about the attachment
     */
    public function show($id)
    {
        return $this->get('/attachments/' . urlencode(strval($id)) . '.json');
    }

    /**
     * Get attachment content as a binary file.
     *
     * @param int $id the attachment number
     *
     * @return string the attachment content
     */
    public function download($id)
    {
        return $this->get('/attachments/download/' . urlencode(strval($id)), false);
    }

    /**
     * Upload a file to redmine.
     *
     * @see http://www.redmine.org/projects/redmine/wiki/Rest_api#Attaching-files
     * available $params :
     * - filename: filename of the attachment
     *
     * @param string $attachment the attachment content
     * @param array  $params     optional parameters to be passed to the api
     *
     * @return string information about the attachment
     */
    public function upload($attachment, $params = [])
    {
        return $this->post(
            PathSerializer::create('/uploads.json', $params)->getPath(),
            $attachment
        );
    }

    /**
     * Delete an attachment.
     *
     * @see https://www.redmine.org/projects/redmine/wiki/Rest_Attachments#DELETE
     *
     * @param int $id id of the attachment
     *
     * @return false|\SimpleXMLElement|string
     */
    public function remove($id)
    {
        return $this->delete('/attachments/' . urlencode(strval($id)) . '.xml');
    }
}
