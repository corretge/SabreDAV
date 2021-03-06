<?php

class Sabre_CalDAV_SharingPluginTest extends Sabre_DAVServerTest {

    protected $setupCalDAV = true;
    protected $setupCalDAVSharing = true;
    protected $setupACL = true;
    protected $autoLogin = 'user1';

    function setUp() {

        $this->caldavCalendars = array(
            array(
                'principaluri' => 'principals/user1',
                'id' => 1,
                'uri' => 'cal1',
                '{http://sabredav.org/ns}sharing-enabled' => true,
            ),
            array(
                'principaluri' => 'principals/user1',
                'id' => 2,
                'uri' => 'cal2',
                '{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}shared-url' => 'calendars/user1/cal2',
                '{http://sabredav.org/ns}owner-principal' => 'principals/user2',
                '{http://sabredav.org/ns}read-only' => 'true',
            ),
            array(
                'principaluri' => 'principals/user1',
                'id' => 3,
                'uri' => 'cal3',
                '{http://sabredav.org/ns}sharing-enabled' => false,
            ),
        ); 

        parent::setUp();

    }

    function testSimple() {

        $this->assertInstanceOf('Sabre_CalDAV_SharingPlugin', $this->server->getPlugin('caldav-sharing'));

    }

    function testGetFeatures() {

        $this->assertEquals(array('calendarserver-sharing'), $this->caldavSharingPlugin->getFeatures());

    }

    function testBeforeGetShareableCalendar() {

        // Forcing the server to authenticate:
        $this->authPlugin->beforeMethod('GET','');
        $props = $this->server->getProperties('calendars/user1/cal1', array(
            '{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}invite',
            '{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}allowed-sharing-modes',
        ));

        $this->assertInstanceOf('Sabre_CalDAV_Property_Invite', $props['{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}invite']);
        $this->assertInstanceOf('Sabre_CalDAV_Property_AllowedSharingModes', $props['{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}allowed-sharing-modes']);

    }

    function testBeforeGetSharedCalendar() {

        $props = $this->server->getProperties('calendars/user1/cal2', array(
            '{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}shared-url',
        ));

        $this->assertInstanceOf('Sabre_DAV_Property_IHref', $props['{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}shared-url']);

    }

    function testUpdateProperties() {

        $result = $this->server->updateProperties('calendars/user1/cal3', array(
            '{DAV:}resourcetype' => new Sabre_DAV_Property_ResourceType(array(
                '{' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '}shared-owner'
            ))
        ));

        $this->assertEquals(array(
            200 => array(
                '{DAV:}resourcetype' => null,
            ),
            'href' => 'calendars/user1/cal3',
        ), $result);

    }

    function testUpdatePropertiesPassThru() {

        $result = $this->server->updateProperties('calendars/user1/cal3', array(
            '{DAV:}foo' => 'bar',
        ));

        $this->assertEquals(array(
            403 => array(
                '{DAV:}foo' => null,
            ),
            'href' => 'calendars/user1/cal3',
        ), $result);

    }

    function testUnknownMethodNoPOST() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'PATCH',
            'REQUEST_URI'    => '/',
        ));

        $response = $this->request($request);

        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testUnknownMethodNoXML() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/',
            'CONTENT_TYPE'   => 'text/plain',
        ));

        $response = $this->request($request);

        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testUnknownMethodNoNode() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/foo',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $response = $this->request($request);

        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testShareRequest() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:share xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:">
    <cs:set>
        <d:href>mailto:joe@example.org</d:href>
        <cs:common-name>Joe Shmoe</cs:common-name>
        <cs:read-write />
    </cs:set>
    <cs:remove>
        <d:href>mailto:nancy@example.org</d:href>
    </cs:remove>
</cs:share>
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 200 OK', $response->status, $response->body);

        $this->assertEquals(array(array(
            'href' => 'mailto:joe@example.org',
            'commonName' => 'Joe Shmoe',
            'readOnly' => false,
            'status' => Sabre_CalDAV_SharingPlugin::STATUS_NORESPONSE,
            'summary' => '',
        )), $this->caldavBackend->getShares(1));

    }

    function testShareRequestNoShareableCalendar() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal2',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:share xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:">
    <cs:set>
        <d:href>mailto:joe@example.org</d:href>
        <cs:common-name>Joe Shmoe</cs:common-name>
        <cs:read-write />
    </cs:set>
    <cs:remove>
        <d:href>mailto:nancy@example.org</d:href>
    </cs:remove>
</cs:share>
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testInviteReply() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:invite-reply xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:">
    <cs:hosturl><d:href>/principals/owner</d:href></cs:hosturl>
</cs:invite-reply>
';

        $request->setBody($xml);
        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 200 OK', $response->status, $response->body);

    }

    function testInviteBadXML() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:invite-reply xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:">
</cs:invite-reply>
';
        $request->setBody($xml);
        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 400 Bad request', $response->status, $response->body);

    }

    function testInviteWrongUrl() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:invite-reply xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:">
    <cs:hosturl><d:href>/principals/owner</d:href></cs:hosturl>
</cs:invite-reply>
';
        $request->setBody($xml);
        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testPublish() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:publish-calendar xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:" />
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 202 Accepted', $response->status, $response->body);

    }

    function testUnpublish() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal1',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:unpublish-calendar xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:" />
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 200 OK', $response->status, $response->body);

    }

    function testPublishWrongUrl() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal2',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:publish-calendar xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:" />
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testUnpublishWrongUrl() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal2',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:unpublish-calendar xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:" />
';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }

    function testUnknownXmlDoc() {

        $request = new Sabre_HTTP_Request(array(
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI'    => '/calendars/user1/cal2',
            'CONTENT_TYPE'   => 'text/xml',
        ));

        $xml = '<?xml version="1.0"?>
<cs:foo-bar xmlns:cs="' . Sabre_CalDAV_Plugin::NS_CALENDARSERVER . '" xmlns:d="DAV:" />';

        $request->setBody($xml);

        $response = $this->request($request);
        $this->assertEquals('HTTP/1.1 501 Not Implemented', $response->status, $response->body);

    }
}
