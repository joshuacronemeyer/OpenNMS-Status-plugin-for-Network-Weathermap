<?php
//
// This is a Open NMS plugin for getting up/down status of the 
// primary interface on a node
// TARGET onms:node_id

class WeatherMapDataSource_onms extends WeatherMapDataSource {

	function Init(&$map)
	{
		return(TRUE);
	}


	function Recognise($targetstring)
	{
		if(preg_match("/^onms:(\d+)$/",$targetstring,$matches))
		{
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}
	
	function IsPrimaryInterface($ip_interface)
	{
		$is_primary = $ip_interface->getAttribute("snmpPrimary");
		return ($is_primary == "P");
	}
	
	function OpenNMSInterfacesUrl($node_id, $map)
	{
	
		$onmshttps= $map->get_hint('opennms_https');
    $onmshost= $map->get_hint('opennms_host');
    $onmsport= intval($map->get_hint('opennms_port'));
    $onmsbaseurl= $map->get_hint('opennms_base_url');
    $onmsuser= $map->get_hint('opennms_user');
    $onmspass= $map->get_hint('opennms_pass');

    if($onmshttps == "true") { $onmsproto = "https"; } else { $onmsproto = "http"; }
    if($onmsport == 0) $onmsport = 8980;
    if($onmsbaseurl == '') $onmsbaseurl = "/opennms";

    return "${onmsproto}://${onmsuser}:${onmspass}@${onmshost}:${onmsport}${onmsbaseurl}/rest/nodes/$node_id/ipinterfaces";
	}

	function GetInterfaceList($node_id, $map)
	{
		$fetchurl = $this->OpenNMSInterfacesUrl($node_id, $map);
		$fd=fopen($fetchurl, "rb");

		if (! $fd) {
			debug("OpenNMS RrdSummary ReadData: Couldn't open ($fetchurl)");
			return( array('NULL', 'NULL', $data_time));
		}

		$domdoc = new DOMDocument();
		$domdoc->loadXML(stream_get_contents($fd));
		fclose($fd);
		if (! $domdoc) {
			debug("OpenNMS RrdSummary ReadData: Failed to parse XML from ($fetchurl). Returning all NULL with timestamp $data_time. \n");
			return( array('NULL', 'NULL', $data_time));
		}

		return $domdoc->getElementsByTagName("ipInterface");

	}
	
	function IsDown($ip_interface)
	{
		$is_down = $ip_interface->getAttribute("isDown");
		if ($is_down == "true"){
			return 0;
		} else {
			return 1;
		}
	}

	function ReadData($targetstring, &$map, &$item)
	{
		$data[IN] = null;
		$data[OUT] = null;
		$data_time = 1;

		if(preg_match("/^onms:(\d+)$/",$targetstring,$matches))
		{
			$node_id = $matches[1];
		}
		
		$ip_interfaces = $this->GetInterfaceList($node_id, $map);
		foreach ($ip_interfaces as $ip_interface) {
			if ($this->IsPrimaryInterface($ip_interface)){
				$status = $this->IsDown($ip_interface);
				$item->add_note("node_status", $status);
				$data[IN] = $status;
				$data[OUT] = $status;
			}
		}

		return( array($data[IN], $data[OUT], $data_time) );
	}
}

// vim:ts=4:sw=4:
?>
