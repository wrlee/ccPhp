<?php
use ccPhp\ccSimpleController;

/**
 * Ajax rendering class for USPS app's services. 
 */
class uspsAjaxController
	extends ccSimpleController
{
	protected $uid='334EVOLU2607';
	protected $pw='681GW03PJ680';
	const SVC_URL_TEST1='http://production.shippingapis.com';
	const SVC_URL_TEST2='https://secure.shippingapis.com';
	const SVC_URL_PATH='/ShippingAPITest.dll';

	protected $form_fields;

	/**
	 * Build USPS service URL based on service name and XML content to be passed.
	 * @param  string $API_Name Service endpoint
	 * @param  string $xml      USPS package content.
	 * @return string           USPS service URL
	 */
	protected function getSvcUrl($API_Name,$xml)
	{
		$qstring='API='.urlencode($API_Name).'&XML='.urlencode($xml);
		$url=self::SVC_URL_TEST1.self::SVC_URL_PATH.'?'.$qstring;
		return $url;
	} // getSvcUrl()

	/**
	 * Call USPS API and convert result to JSON.
	 * @param  string  $API_Name USPS service name
	 * @param  string  $xml      XML content for service request
	 * @param  int|false $idx    Client Index of entry querying for (if applicable)
	 * @return JSON            JSON version of USPS response
	 */
	protected function prepareAndSubmit($API_Name, $xml, $idx=false)
	{
		$url = $this->getSvcUrl($API_Name, $xml);
		$content = file_get_contents($url);

		$result = simplexml_load_string($content);

		if ($idx !== false) 
			$result->addChild('idx',$idx);
		$json = json_encode($result);
		ccTrace::tr($json);
		return $json;
	} // prepareAndSubmit()

	/**
	 * Validate that this is an AJAX request (since the same URLs service the HTML page requests)
	 * @param  ccRequest $request Current request 
	 * @return true URL rendered
	 * @return false URL not rendered
	 */
	public function begin($request)
	{
		$ajax = $request->isAjax();
		if ($ajax) {
			$this->form_fields = $request->getRequestVars();
			if (empty($this->form_fields)) return false;	// Assume HTML was intended
		}
		ccTrace::tr($request);
		return $ajax && $request->getFormat() == 'json';
	}
	/**
	 * Lookup city/state based on zipcode. 
	 *
	 * @todo The USPS API only handles 5 requests at a time, if more requests 
	 * come in than that, multiple calls must be made. This should handle that
	 * scenario.
	 * @param ccRequest $request HTTP request block
	 */
	public function CityStateLookup($request)
	{
		if (isset($this->form_fields['zip']) && $this->form_fields['zip']) {
		//	header('Content-Type: text/xml');

			$XML_String_containing_User_ID='<CityStateLookupRequest USERID="'.$this->uid.'">';
			if (is_array($this->form_fields['zip'])) 
				foreach ($this->form_fields['zip'] as $id => $zip) {
					$XML_String_containing_User_ID.='<ZipCode ID="'.$id.'"><Zip5>'.$zip.'</Zip5></ZipCode>';
				}
			else
				$XML_String_containing_User_ID.='<ZipCode ID="0"><Zip5>'.$this->form_fields['zip'].'</Zip5></ZipCode>';

			$XML_String_containing_User_ID.='</CityStateLookupRequest>';

			ccTrace::tr($XML_String_containing_User_ID);
			$result = $this->prepareAndSubmit('CityStateLookup',$XML_String_containing_User_ID,
											  isset($this->form_fields['idx'])?$this->form_fields['idx']:false);
			echo $result;
		}

		return true;
	} // function CityStateLookup

	public function AddressValidate($request)
	{
		$XML_String_containing_User_ID='<AddressValidateRequest USERID="'.$this->uid.'">';
		// if (is_array($this->form_fields['zip'])) 
		// 	foreach ($this->form_fields['zip'] as $id => $zip) {
		// 		$XML_String_containing_User_ID.='<ZipCode ID="'.$id.'"><Zip5>'.$zip.'</Zip5></ZipCode>';
		// 	}
		// else

		$XML_String_containing_User_ID.='<Address ID="0">';
		$XML_String_containing_User_ID.=	'<Address1>'.(isset($this->form_fields['address1']) ? $this->form_fields['address1'] : '').'</Address1>';
		$XML_String_containing_User_ID.=	'<Address2>'.(isset($this->form_fields['address2']) ? $this->form_fields['address2'] : '').'</Address2>';
		$XML_String_containing_User_ID.=	'<City>'.(isset($this->form_fields['city']) ? $this->form_fields['city'] : '').'</City>';
		$XML_String_containing_User_ID.=	'<State>'.(isset($this->form_fields['state']) ? $this->form_fields['state'] : '').'</State>';
		$XML_String_containing_User_ID.=	'<Zip5></Zip5>';
		$XML_String_containing_User_ID.=	'<Zip4></Zip4>';
		$XML_String_containing_User_ID.='</Address>';
		$XML_String_containing_User_ID.='</AddressValidateRequest>';

		ccTrace::tr($XML_String_containing_User_ID);

		$result = $this->prepareAndSubmit('Verify',$XML_String_containing_User_ID,
										  isset($this->form_fields['idx'])?$this->form_fields['idx']:false);
		ccTrace::tr($result);
		echo $result;

		return true;
	}

	public function ZipCodeLookup($request)
	{
		$XML_String_containing_User_ID='<ZipCodeLookup USERID="'.$this->uid.'">';
		$XML_String_containing_User_ID.='<Address>';
		$XML_String_containing_User_ID.=	'<Address1>'.(isset($this->form_fields['address1']) ? $this->form_fields['address1'] : '').'</Address1>';
		$XML_String_containing_User_ID.=	'<Address2>'.(isset($this->form_fields['address2']) ? $this->form_fields['address2'] : '').'</Address2>';
		$XML_String_containing_User_ID.=	'<City>'.(isset($this->form_fields['city']) ? $this->form_fields['city'] : '').'</City>';
		$XML_String_containing_User_ID.=	'<State>'.(isset($this->form_fields['state']) ? $this->form_fields['state'] : '').'</State>';
		$XML_String_containing_User_ID.='</Address>';
		$XML_String_containing_User_ID.='</ZipCodeLookup>';

		$result = $this->prepareAndSubmit('ZipCodeLookup',$XML_String_containing_User_ID,
									 	  isset($this->form_fields['idx'])?$this->form_fields['idx']:false);
		echo $result;

		return true;
	}
} // class uspsAjaxController
