<?php 
namespace Libs;

use Carbon\Carbon;

use App\Exceptions\CarriersException;

use App\Models\Merchant;
use App\Models\Pickup;
use SimpleXMLElement;

class DHL
{
    private static $xsd = [
        'BookPURequest' => 'http://www.dhl.com book-pickup-global-req_EA.xsd',
        'CancelPURequest' => 'http://www.dhl.com cancel-pickup-global-req.xsd',
        'ShipmentRequest' => 'http://www.dhl.com ship-val-global-req.xsd'
    ];
    
    private static $schemaVersion = [
        'BookPURequest' => '3.0',
        'CancelPURequest' => '3.0',
        'ShipmentRequest' => '10.0'
    ];

    private static $stagingUrl = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';
    private static $productionUrl = 'https://xmlpi-ea.dhl.com/XMLShippingServlet?isUTF8Support=true';

    private $end_point;
    private $account_number;
    function __construct() {
        $this->config = [
            'MessageTime' => Carbon::now()->format(Carbon::ATOM),
            'MessageReference' => randomNumber(32),
            'SiteID' => config('carriers.dhl.SITE_ID'),
            'Password' =>  config('carriers.dhl.PASSWORD')
        ];

        $this->end_point = self::$stagingUrl;
        $this->account_number = config('carriers.dhl.ACCOUNT_NUMBER');
    }
    
    public function createPickup($email,$date,$address)
    {
        $payload = $this->bindJsonFile('pickup.create.json');
        
        $payload['Requestor']['AccountNumber'] = $this->account_number;

        $payload['Requestor']['CompanyName'] =  $address['name'];
        $payload['Requestor']['Address1'] = $address['name'];
        $payload['Requestor']['City'] = $address['name'];
        $payload['Requestor']['CountryCode'] = $address['country_code'];
        $payload['Requestor']['PostalCode'] = $address['name'];
        $payload['Requestor']['RequestorContact']['PersonName'] = $address['name'];
        $payload['Requestor']['RequestorContact']['Phone'] =  $address['phone'];
    
        $payload['Place']['CompanyName'] = $address['name'];
        $payload['Place']['Address1'] = $address['city'];
        $payload['Place']['Address2'] = $address['city'];
        $payload['Place']['PackageLocation'] = $address['city'];
        $payload['Place']['City'] = $address['city'];
        $payload['Place']['CountryCode'] = $address['country_code'];
        $payload['Place']['PostalCode'] = "";


        // Pickup
        $payload['Pickup']['PickupDate'] = $date;
        $payload['Pickup']['ReadyByTime'] = '15:00';
        $payload['Pickup']['CloseTime'] = '16:00';

        $payload['PickupContact']['PersonName'] = $address['name'];
        $payload['PickupContact']['Phone'] = $address['phone'];

        $payload['ShipmentDetails']['AccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['BillToAccountNumber'] = $this->account_number;
        $payload['ShipmentDetails']['AWBNumber'] = randomNumber(9);

        $payload['ConsigneeDetails']['CompanyName'] = $address['name'];
        $payload['ConsigneeDetails']['AddressLine'] = $address['area'];
        $payload['ConsigneeDetails']['City'] = $address['city'];
        $payload['ConsigneeDetails']['CountryCode'] =  $address['country_code'];
        $payload['ConsigneeDetails']['PostalCode'] = '';
        $payload['ConsigneeDetails']['Contact']['PersonName'] = $address['name'];
        $payload['ConsigneeDetails']['Contact']['Phone'] = $address['phone'];

        $response = $this->call('BookPURequest',$payload);
        
        if(isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Pickup – Something Went Wrong');
        return ['id' => $this->config['MessageReference'] , 'guid' => $response['ConfirmationNumber']];
    }

    public function cancelPickup($pickupInfo)
    {
        $address = Merchant::getAdressInfoByID($pickupInfo->address_id);
        $payload = $this->bindJsonFile('pickup.cancel.json');

        $payload['RegionCode'] = 'EU';
        $payload['ConfirmationNumber'] = $pickupInfo->hash;
        $payload['RequestorName'] = $address->name;
        $payload['CountryCode'] = $address->country_code;
        $payload['PickupDate'] = $pickupInfo->pickup_date;
        $payload['CancelTime'] = '10:20';

        $response = $this->call('CancelPURequest',$payload);
        if(isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Cancel Pickup – Something Went Wrong');

        return true;
    }

    public function printLabel(){}

    public function createShipment($merchentInfo,$address,$shipmentInfo)
    {
        $payload = $this->bindJsonFile('shipment.create.json');

        $payload['Billing']['ShipperAccountNumber'] = $this->account_number;
        $payload['Billing']['BillingAccountNumber'] = $this->account_number;
        
        $payload['Consignee']['CompanyName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['AddressLine1'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['AddressLine2'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['AddressLine3'] = $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['StreetName'] =  $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['BuildingName'] =  $shipmentInfo['consignee_address_description'];
        $payload['Consignee']['StreetNumber'] = 'XYZ';

        $payload['Consignee']['City'] = $shipmentInfo['consignee_city'];
        $payload['Consignee']['PostalCode'] = $shipmentInfo['consignee_zip_code'] ?? '';
        $payload['Consignee']['CountryCode'] = $shipmentInfo['consignee_country'];
        $payload['Consignee']['CountryName'] = $shipmentInfo['consignee_country'];

        $payload['Consignee']['Contact']['PersonName'] = $shipmentInfo['consignee_name'];
        $payload['Consignee']['Contact']['PhoneNumber'] = $shipmentInfo['consignee_phone'];
        $payload['Consignee']['Contact']['MobilePhoneNumber'] = $shipmentInfo['consignee_second_phone'];
        $payload['Consignee']['Contact']['Email'] = $shipmentInfo['consignee_email'];
        $payload['Consignee']['Contact']['PhoneExtension'] = '';

        $payload['ShipmentDetails']['Contents'] = $shipmentInfo['content'];
        $payload['ShipmentDetails']['Date']= Carbon::now()->format('Y-m-d');
        
        $payload['Shipper']['ShipperID'] = $merchentInfo->id;
        $payload['Shipper']['CompanyName'] = $address['name'];
        $payload['Shipper']['AddressLine1'] = $address['description'];
        $payload['Shipper']['AddressLine2'] = $address['area'];
        $payload['Shipper']['AddressLine3'] = $address['area'];
        $payload['Shipper']['City'] = $address['city_code'];
        $payload['Shipper']['CountryCode'] =  $merchentInfo->country_code;
        $payload['Shipper']['CountryName'] = $merchentInfo->country_code;
        $payload['Shipper']['StreetName'] =  $address['area'];
        $payload['Shipper']['BuildingName'] =  $address['area'];
        $payload['Shipper']['StreetNumber'] = 'XYZ';

        $payload['Shipper']['Contact']['PersonName'] = $address['name'];
        $payload['Shipper']['Contact']['PhoneNumber'] = $address['phone'];
        $payload['Shipper']['Contact']['MobilePhoneNumber'] = $address['phone'];
        $payload['Shipper']['Contact']['Email'] = $merchentInfo->email;

        $response = $this->call('ShipmentRequest',$payload);
        if(isset($response['Response']['Status']) && $response['Response']['Status']['ActionStatus'] == 'Error')
            throw new CarriersException('DHL Create Shipment – Something Went Wrong');

        return [
            'id' => $response['DHLRoutingCode'],
            'file' => uploadFiles('dhl/shipment',base64_decode($response['LabelImage']['OutputImage']),'pdf',true)
        ];
    }

    public function shipmentArray($merchentInfo,$address,$shipmentInfo){
        return $this->createShipment($merchentInfo,$address,$shipmentInfo);
    }

    public function bindJsonFile($file)
    {
        $payload = json_decode(file_get_contents(storage_path().'/../App/Libs/DHL/'.$file),true);
        $payload['Request']['ServiceHeader'] = $this->config;

        return $payload;
    }

    private function dhlXMLFile($type,$data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'."<req:$type></req:$type>", LIBXML_NOERROR, false, 'ws', true);
        $xml->addAttribute('req:xmlns:req', 'http://www.dhl.com');
        $xml->addAttribute('req:xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $xml->addAttribute('req:xsi:schemaLocation', self::$xsd[$type]);
        $xml->addAttribute('req:schemaVersion', self::$schemaVersion[$type]);
        return array_to_xml($data,$xml);
    }

    private function call($type,$data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->end_point);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '               
<req:ShipmentRequest xsi:schemaLocation="http://www.dhl.com ship-val-global-req.xsd" schemaVersion="10.0" xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<Request>
   <ServiceHeader>
      <MessageTime>2020-12-18T15:35:39.4700875+08:00</MessageTime>
      <MessageReference>1234567890123456789012345678901</MessageReference>
      <SiteID>v62_9QMrWmuwJL</SiteID>
      <Password>HUoC706KpI</Password>
   </ServiceHeader>
   <MetaData>
      <SoftwareName>test</SoftwareName>
      <SoftwareVersion>10.0</SoftwareVersion>
   </MetaData>
</Request>
<RegionCode>AM</RegionCode>
<LanguageCode>en</LanguageCode>
<Billing>
   <ShipperAccountNumber>951237760</ShipperAccountNumber>
   <ShippingPaymentType>S</ShippingPaymentType>
   <BillingAccountNumber>951237760</BillingAccountNumber>
</Billing>
<Consignee>
   <CompanyName>Test Farfetch</CompanyName>
   <AddressLine1>100 HWY 16 QUEEN CHARLOTTE BC</AddressLine1>
   <AddressLine2>AddressLine2 Test2</AddressLine2>
   <AddressLine3>AddressLine3 Test3</AddressLine3>
   <City>GAILDORF</City>
   <PostalCode>74405</PostalCode>
   <CountryCode>DE</CountryCode>
   <CountryName>GERMANY</CountryName>
   <Contact>
      <PersonName>POUL HANSEN</PersonName>
      <PhoneNumber>12172762192</PhoneNumber>
      <PhoneExtension/>
      <FaxNumber>0000</FaxNumber>
      <Email>POUL.HANSEN@TESTMAIL1.COM</Email>
      <MobilePhoneNumber>+2177979799</MobilePhoneNumber>
   </Contact>
  <StreetName>MARGUERITE ROAD</StreetName>
     <BuildingName>GIOTTO TOWER</BuildingName>
     <StreetNumber>36A/P</StreetNumber>
  <RegistrationNumbers>
      <RegistrationNumber>
         <Number>97796889679</Number>
         <NumberTypeCode>VAT</NumberTypeCode>
         <NumberIssuerCountryCode>IE</NumberIssuerCountryCode>
      </RegistrationNumber>
      <RegistrationNumber>
         <Number>977961111</Number>
         <NumberTypeCode>EOR</NumberTypeCode>
         <NumberIssuerCountryCode>IE</NumberIssuerCountryCode>
      </RegistrationNumber>
   </RegistrationNumbers>
   <BusinessPartyTypeCode>BU</BusinessPartyTypeCode>
</Consignee>
<Dutiable>
   <DeclaredValue>113.225</DeclaredValue>
   <DeclaredCurrency>USD</DeclaredCurrency>
   <ShipperEIN>ShipperEIN12021</ShipperEIN>
   <TermsOfTrade>DAP</TermsOfTrade>
</Dutiable>
<UseDHLInvoice>Y</UseDHLInvoice>
<DHLInvoiceLanguageCode>en</DHLInvoiceLanguageCode>
<DHLInvoiceType>CMI</DHLInvoiceType>
<ExportDeclaration>
   <InterConsignee>Cosg32424</InterConsignee>
   <IsPartiesRelation>N</IsPartiesRelation>
   <SignatureName>Madison May</SignatureName>
   <SignatureTitle>Manager</SignatureTitle>
   <ExportReason>sales</ExportReason>
   <ExportReasonCode>P</ExportReasonCode>    
   <InvoiceNumber>MyDHLAPI - INV-001</InvoiceNumber>
   <InvoiceDate>2021-11-11</InvoiceDate>
   <Remarks>Invoice Remarks</Remarks>
   <OtherCharges>
      <OtherCharge>
         <OtherChargeCaption>Freight Charges</OtherChargeCaption>
         <OtherChargeValue>12.50</OtherChargeValue>
         <OtherChargeType>FRCST</OtherChargeType>
      </OtherCharge>
      <OtherCharge>
             <OtherChargeCaption>Handlingcare</OtherChargeCaption>
             <OtherChargeValue>7.25</OtherChargeValue>
             <OtherChargeType>HRCRG</OtherChargeType>
         </OtherCharge>
   </OtherCharges>
   <TermsOfPayment>60 days</TermsOfPayment>
   <SignatureImage>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</SignatureImage>
   <ReceiverReference>ReceiverReference</ReceiverReference>
   <ExporterId>43244325</ExporterId>
   <ExporterCode>ExporterCode</ExporterCode>
   <PackageMarks>PackageMarks</PackageMarks>
   <OtherRemarks2>OtherRemarks2</OtherRemarks2>
   <OtherRemarks3>OtherRemarks3</OtherRemarks3>
   <AddDeclText1>I DECLARE ALL INFORMATION TRUE AND CORRECT</AddDeclText1>
   <ExportLineItem>
      <LineNumber>1</LineNumber>
      <Quantity>1</Quantity>
      <QuantityUnit>PCS</QuantityUnit>
      <Description>Fall collection: knitted vegetarian top white US 5</Description>
      <Value>56.525</Value>
      <CommodityCode>6109.10.0010</CommodityCode>
      <Weight>
         <Weight>0.200</Weight>
         <WeightUnit>K</WeightUnit>
      </Weight>
      <GrossWeight>
         <Weight>0.250</Weight>
         <WeightUnit>K</WeightUnit>
      </GrossWeight>
      <ManufactureCountryCode>US</ManufactureCountryCode>
      <ImportCommodityCode>6109.10.0010</ImportCommodityCode>
      <ItemReferences>
         <ItemReference>
            <ItemReferenceType>PAN</ItemReferenceType>
            <ItemReferenceNumber>10597122</ItemReferenceNumber>
         </ItemReference>
         <ItemReference>
            <ItemReferenceType>AFE</ItemReferenceType>
            <ItemReferenceNumber>105972112200</ItemReferenceNumber>
         </ItemReference>
      </ItemReferences>
      <CustomsPaperworks>
         <CustomsPaperwork>
            <CustomsPaperworkType>INV</CustomsPaperworkType>
            <CustomsPaperworkID>MyDHLAPI - LN#1-CUSDOC-001</CustomsPaperworkID>
         </CustomsPaperwork>
      </CustomsPaperworks>
   </ExportLineItem>
   <ExportLineItem>
      <LineNumber>2</LineNumber>
      <Quantity>2</Quantity>
      <QuantityUnit>PCS</QuantityUnit>
      <Description>Hamsa embellished knitted top / Knitwear / Clothing</Description>
      <Value>28.35</Value>
      <CommodityCode>6109.10.0010</CommodityCode>
      <Weight>
         <Weight>0.200</Weight>
         <WeightUnit>K</WeightUnit>
      </Weight>
      <GrossWeight>
         <Weight>0.250</Weight>
         <WeightUnit>K</WeightUnit>
      </GrossWeight>
      <ManufactureCountryCode>US</ManufactureCountryCode>
      <ImportCommodityCode>6109.10.0010</ImportCommodityCode>
      <ItemReferences>
         <ItemReference>
            <ItemReferenceType>PAN</ItemReferenceType>
            <ItemReferenceNumber>1299211</ItemReferenceNumber>
         </ItemReference>
         <ItemReference>
            <ItemReferenceType>AFE</ItemReferenceType>
            <ItemReferenceNumber>12865306</ItemReferenceNumber>
         </ItemReference>
      </ItemReferences>
      <CustomsPaperworks>
         <CustomsPaperwork>
            <CustomsPaperworkType>INV</CustomsPaperworkType>
            <CustomsPaperworkID>MyDHLAPI - LN#2-CUSDOC-001</CustomsPaperworkID>
         </CustomsPaperwork>
      </CustomsPaperworks>
   </ExportLineItem>
   <InvoiceInstructions>This is invoice instruction</InvoiceInstructions>
   <PlaceOfIncoterm>GAILDORF PORT</PlaceOfIncoterm>
   <ShipmentPurpose>COMMERCIAL</ShipmentPurpose>
   <DocumentFunction>IMPORT</DocumentFunction>
   <CustomsDocuments>
      <CustomsDocument>
         <CustomsDocumentType>INV</CustomsDocumentType>
         <CustomsDocumentID>MyDHLAPI - CUSDOC-001</CustomsDocumentID>
      </CustomsDocument>
   </CustomsDocuments>
   <InvoiceTotalNetWeight>0.400</InvoiceTotalNetWeight>
   <InvoiceTotalGrossWeight>0.500</InvoiceTotalGrossWeight>
   <InvoiceReferences>
      <InvoiceReference>
         <InvoiceReferenceType>OID</InvoiceReferenceType>
         <InvoiceReferenceNumber>OID-7839749374</InvoiceReferenceNumber>
      </InvoiceReference>
      <InvoiceReference>
         <InvoiceReferenceType>UCN</InvoiceReferenceType>
         <InvoiceReferenceNumber>UCN-76498376498</InvoiceReferenceNumber>
      </InvoiceReference>
      <InvoiceReference>
         <InvoiceReferenceType>CU</InvoiceReferenceType>
         <InvoiceReferenceNumber>MyDHLAPI - CUREF-001</InvoiceReferenceNumber>
      </InvoiceReference>
   </InvoiceReferences>
</ExportDeclaration>
<Reference>
   <ReferenceID>ShipLevelTest2</ReferenceID>
   <ReferenceType>OBC</ReferenceType>
</Reference>
<ShipmentDetails>
   <Pieces>
      <Piece>
         <PieceID>1</PieceID>
         <PackageType>EE</PackageType>
         <Weight>0.250</Weight>
         <Width>20</Width>
         <Height>15</Height>
         <Depth>30</Depth>
         <PieceContents>1st Piece clothing</PieceContents>
         <PieceReference>
            <ReferenceID>Piece1Reference</ReferenceID>
            <ReferenceType>PK2</ReferenceType>
         </PieceReference>
      </Piece>
      <Piece>
         <PieceID>2</PieceID>
         <PackageType>YP</PackageType>
         <Weight>0.250</Weight>
         <Width>20</Width>
         <Height>10</Height>
         <Depth>10</Depth>
         <PieceContents>shirt</PieceContents>
         <PieceReference>
            <ReferenceID>Piece2Reference</ReferenceID>
            <ReferenceType>PK2</ReferenceType>
         </PieceReference>
      </Piece>
   </Pieces>
   <WeightUnit>K</WeightUnit>
   <GlobalProductCode>P</GlobalProductCode>
   <LocalProductCode>P</LocalProductCode>
   <Date>2021-11-11</Date>
   <Contents>v10.0 001 - parcel with clothing</Contents>
   <DimensionUnit>C</DimensionUnit>
   <PackageType>PA</PackageType>
   <IsDutiable>Y</IsDutiable>
   <CurrencyCode>EUR</CurrencyCode>
</ShipmentDetails>
<Shipper>
   <ShipperID>XXXXXXXXX</ShipperID>
   <CompanyName>UAT v10.0 001 MyDHLAPI</CompanyName>
   <AddressLine1>Unit 603, 05-07,</AddressLine1>
   <AddressLine2>Bio-Informatics Centre</AddressLine2>
   <AddressLine3>Testing Streeet</AddressLine3>
   <City>ATLANTIS</City>
   <DivisionCode>FL</DivisionCode>
   <PostalCode>33463</PostalCode>
   <CountryCode>US</CountryCode>
   <CountryName>UNITED STATES</CountryName>
   <Contact>
         <PersonName>ANDREA OLIVIA</PersonName>
         <PhoneNumber>1217-276-2192</PhoneNumber>
         <PhoneExtension>6536</PhoneExtension>
         <FaxNumber>17456356365</FaxNumber>
         <Telex>74558</Telex>
         <Email>ANDREA.OLIVIA@TESTMAIL1.COM</Email>
         <MobilePhoneNumber>13056483896</MobilePhoneNumber>
     </Contact>
     <StreetName>BOUSTEAD ROAD</StreetName>
     <BuildingName>VELASCA TOWER</BuildingName>
     <StreetNumber>46A/26</StreetNumber>
   <RegistrationNumbers>
      <RegistrationNumber>
         <Number>IM0401234560</Number>
         <NumberTypeCode>FED</NumberTypeCode>
         <NumberIssuerCountryCode>US</NumberIssuerCountryCode>
      </RegistrationNumber>
      <RegistrationNumber>
         <Number>VAT23249923</Number>
         <NumberTypeCode>VAT</NumberTypeCode>
         <NumberIssuerCountryCode>US</NumberIssuerCountryCode>
      </RegistrationNumber>
   </RegistrationNumbers>
   <BusinessPartyTypeCode>BU</BusinessPartyTypeCode>
</Shipper>
<SpecialService>
   <SpecialServiceType>DD</SpecialServiceType>
</SpecialService>
<EProcShip>N</EProcShip>
<LabelImageFormat>PDF</LabelImageFormat>
<RequestArchiveDoc>Y</RequestArchiveDoc>
<NumberOfArchiveDoc>1</NumberOfArchiveDoc>
<Label>
   <HideAccount>Y</HideAccount>
   <LabelTemplate>8X4_thermal</LabelTemplate>
   <CustomsInvoiceTemplate>COMMERCIAL_INVOICE_P_10</CustomsInvoiceTemplate>
   <Logo>Y</Logo>
   <CustomerLogo>
      <LogoImage>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</LogoImage>
      <LogoImageFormat>JPG</LogoImageFormat>
   </CustomerLogo>
</Label>
<GetPriceEstimate>N</GetPriceEstimate>
<SinglePieceImage>N</SinglePieceImage>
 <Importer>
     <CompanyName>DAVID CO. Name</CompanyName>
     <AddressLine1>19th Floor, Plaza IBM</AddressLine1>
     <AddressLine2>No. 8, First Avenue,</AddressLine2>
     <AddressLine3>Kingsford Drive</AddressLine3>
     <City>Hounslow</City>
     <PostalCode>TW4 6JS</PostalCode>
     <CountryCode>GB</CountryCode>
     <CountryName>United Kingdom</CountryName>
     <Contact>
         <PersonName>DAVID GOW</PersonName>
         <PhoneNumber>353 1 235 2369</PhoneNumber>
         <PhoneExtension>45232</PhoneExtension>
         <FaxNumber>11234325423</FaxNumber>
         <Telex>454586</Telex>
         <Email>DAVID.GOW@GMAIL.COM</Email>
         <MobilePhoneNumber>8978967878</MobilePhoneNumber>
     </Contact>
     <StreetName>GLASNEVIN ROAD</StreetName>
     <BuildingName>BOSCO TOWER</BuildingName>
     <StreetNumber>10C/A</StreetNumber>
     <RegistrationNumbers>
         <RegistrationNumber>
             <Number>IP-VAT-001</Number>
             <NumberTypeCode>VAT</NumberTypeCode>
             <NumberIssuerCountryCode>GB</NumberIssuerCountryCode>
         </RegistrationNumber>
         <RegistrationNumber>
             <Number>IP-DAN-001</Number>
             <NumberTypeCode>DAN</NumberTypeCode>
             <NumberIssuerCountryCode>GB</NumberIssuerCountryCode>
         </RegistrationNumber>
     </RegistrationNumbers>
     <BusinessPartyTypeCode>BU</BusinessPartyTypeCode>
 </Importer>
 <Exporter>
     <CompanyName>THOMAS CO. Name</CompanyName>
     <SuiteDepartmentName>aabc 453</SuiteDepartmentName>
     <AddressLine1>Exp Ad Ln1, Rounding Off Address 10003 St Cor</AddressLine1>
     <AddressLine2>Exp Ad Ln2, Spin off Drive Privet St, Bern Ci</AddressLine2>
     <AddressLine3>Exp Ad Ln3, ty Province of Plateness, 2900011</AddressLine3>
     <City>QUITAQUE</City>
     <PostalCode>79255</PostalCode>
     <CountryCode>US</CountryCode>
     <CountryName>UNITED STATE</CountryName>
     <Contact>
         <PersonName>THOMAS PEDERSEN</PersonName>
         <PhoneNumber>17243557355</PhoneNumber>
         <PhoneExtension>75495</PhoneExtension>
         <FaxNumber>4232094870</FaxNumber>
         <Telex>0080</Telex>
         <Email>THOMAS.PEDERSEN@GMAIL.COM</Email>
         <MobilePhoneNumber>4325466664325</MobilePhoneNumber>
     </Contact>
     <StreetName>MAHSURI ROAD</StreetName>
     <BuildingName>RADISSON TOWER</BuildingName>
     <StreetNumber>10/36A</StreetNumber>
     <RegistrationNumbers>
      <RegistrationNumber>
         <Number>233968896791291-134342-12319-121239</Number>
         <NumberTypeCode>VAT</NumberTypeCode>
         <NumberIssuerCountryCode>US</NumberIssuerCountryCode>
      </RegistrationNumber>
   </RegistrationNumbers>
     <BusinessPartyTypeCode>BU</BusinessPartyTypeCode>
 </Exporter>
 <Seller>
     <CompanyName>SL CompanyName</CompanyName>
     <SuiteDepartmentName>SL STDepartName</SuiteDepartmentName>
     <AddressLine1>SL AddressLine1</AddressLine1>
     <AddressLine2>SL AddressLine2</AddressLine2>
     <AddressLine3>SL AddressLine3</AddressLine3>
     <City>YONKERS</City>
     <Division>Yonkerss</Division>
     <DivisionCode>YK</DivisionCode>
     <PostalCode>10705</PostalCode>
     <CountryCode>US</CountryCode>
     <CountryName>United States of America</CountryName>
     <Contact>
         <PersonName>SL PersonName</PersonName>
         <PhoneNumber>111212133</PhoneNumber>
         <PhoneExtension>22212</PhoneExtension>
         <FaxNumber>1111</FaxNumber>
         <Telex>4444</Telex>
         <Email>sl_nonreply@dhl.com</Email>
         <MobilePhoneNumber>3321223124</MobilePhoneNumber>
     </Contact>
     <Suburb>ny</Suburb>
     <StreetName>SL-CI 1/24</StreetName>
     <BuildingName>SL BuilName</BuildingName>
     <StreetNumber>SL StrName</StreetNumber>
     <RegistrationNumbers>
         <RegistrationNumber>
             <Number>SL-1111111</Number>
             <NumberTypeCode>VAT</NumberTypeCode>
             <NumberIssuerCountryCode>US</NumberIssuerCountryCode>
         </RegistrationNumber>
     </RegistrationNumbers>
     <BusinessPartyTypeCode>BU</BusinessPartyTypeCode>
 </Seller>
 <Payer>
     <CompanyName>PY CompanyName</CompanyName>
     <SuiteDepartmentName>PY SuiteDepartname</SuiteDepartmentName>
     <AddressLine1>PY AddressLine1</AddressLine1>
     <AddressLine2>PY AddressLine2</AddressLine2>
     <AddressLine3>PY AddressLine2</AddressLine3>
     <City>LONDON</City>
     <Division>LOD</Division>
     <DivisionCode>LD</DivisionCode>
     <PostalCode>E1 6AN</PostalCode>
     <CountryCode>GB</CountryCode>
     <CountryName>United Kingdom</CountryName>
     <Contact>
         <PersonName>PY PersonName</PersonName>
         <PhoneNumber>11234325423</PhoneNumber>
         <PhoneExtension>1111</PhoneExtension>
         <FaxNumber>1123111312</FaxNumber>
         <Telex>123123</Telex>
         <Email>py@nonreply@dhl.com</Email>
         <MobilePhoneNumber>12312312312</MobilePhoneNumber>
     </Contact>
     <Suburb>London</Suburb>
     <StreetName>PY StrName</StreetName>
     <BuildingName>PY BuilName</BuildingName>
     <StreetNumber>PY-CI 1/24</StreetNumber>
     <RegistrationNumbers>
         <RegistrationNumber>
             <Number>PY-2222222</Number>
             <NumberTypeCode>VAT</NumberTypeCode>
             <NumberIssuerCountryCode>GB</NumberIssuerCountryCode>
         </RegistrationNumber>
     </RegistrationNumbers>
     <BusinessPartyTypeCode>PR</BusinessPartyTypeCode>
 </Payer>
</req:ShipmentRequest>');// $this->dhlXMLFile($type,$data)->asXML());
        $result = curl_exec($ch);
        curl_error($ch);

        return XMLToArray($result);
    }
}