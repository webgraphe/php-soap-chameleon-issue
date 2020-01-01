<?php

function includedXsd($ns = null)
{
    $ns = $ns ? " xmlns=\"{$ns}\"" : '';

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema"{$ns}>
    <xsd:attributeGroup name="CurrencyAmountGroup">
        <xsd:attribute name="Amount" type="xsd:int"/>
        <xsd:attribute name="CurrencyCode" type="xsd:string"/>
    </xsd:attributeGroup>
    <xsd:complexType name="User">
        <xsd:attributeGroup ref="CurrencyAmountGroup" />
    </xsd:complexType>
</xsd:schema>
XML;
}

function importedXsd()
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xsd:schema targetNamespace="http://foo.bar/testserver/types" xmlns="http://foo.bar/testserver/types" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
	<xsd:include schemaLocation="bug-include.xsd" />
	<xsd:element name="User" type="User"/>
</xsd:schema>
XML;
}

function wsdl()
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<definitions name="TestServer" targetNamespace="http://foo.bar/testserver" xmlns:tns="http://foo.bar/testserver"
             xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/"
             xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:ns="http://foo.bar/testserver/types">
    <types>
        <xsd:schema>
            <xsd:import namespace="http://foo.bar/testserver/types" schemaLocation="bug-import.xsd"/>
        </xsd:schema>
    </types>
    <message name="getUserRequest">
        <part name="id" type="xsd:id"/>
    </message>
    <message name="getUserResponse">
        <part name="userReturn" element="ns:User"/>
    </message>
    <portType name="TestServerPortType">
        <operation name="getUser">
            <input message="tns:getUserRequest"/>
            <output message="tns:getUserResponse"/>
        </operation>
    </portType>
    <binding name="TestServerBinding" type="tns:TestServerPortType">
        <soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>
        <operation name="getUser">
            <soap:operation soapAction="http://foo.bar/testserver/#getUser"/>
            <input>
                <soap:body use="literal" namespace="http://foo.bar/testserver"/>
            </input>
            <output>
                <soap:body use="literal" namespace="http://foo.bar/testserver"/>
            </output>
        </operation>
    </binding>
    <service name="TestServerService">
        <port name="TestServerPort" binding="tns:TestServerBinding">
            <soap:address location="http://localhost/wsdl-creator/TestClass.php"/>
        </port>
    </service>
</definitions>
XML;
}

class MockedSoapClient extends SoapClient
{
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Header/>
    <soap:Body>
        <userReturn>
            <User Amount="123" CurrencyCode="USD"/>
        </userReturn>
    </soap:Body>
</soap:Envelope>
XML;
    }
}

function types($test, $ns = null)
{
    file_put_contents(__DIR__ . "/bug-include.xsd", includedXsd($ns));
    file_put_contents(__DIR__ . "/bug-import.xsd", importedXsd());
    file_put_contents(__DIR__ . "/bug.wsdl", wsdl());
    $client = new MockedSoapClient(__DIR__ . "/bug.wsdl", ['cache_wsdl' => WSDL_CACHE_NONE]);
    $response = $client->__soapCall('getUser', []);

    echo "$test\n";
    echo "TYPES: " . trim(implode(PHP_EOL, $client->__getTypes())) . PHP_EOL;
    echo "RESPONSE: " . json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
    echo PHP_EOL;

    unlink(__DIR__ . "/bug-include.xsd");
    unlink(__DIR__ . "/bug-import.xsd");
    unlink(__DIR__ . "/bug.wsdl");
}

types('ACTUAL');
types('EXPECTED', 'http://foo.bar/testserver/types');
