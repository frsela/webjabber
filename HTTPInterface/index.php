<?php

/***************************************************
 * Interfaz de comunicaciones HTTP para SMS PLUS
 * Fernando Rodríguez Sela, Telefónica I+D, 2008
 * SVN HeadURL: $HeadURL: file:///opt1/svn/widgets/trunk/SMSPlus/HTTPInterface/index.php $
 * SVN Id: $Id: index.php 40 2008-04-04 10:57:28Z frsela $
 ***************************************************/

require_once("xmlprocessor.php");
require_once("kernel.php");

$ActivarLOG = true;
// Identificador único para utilizar en los logs
$uuid = uniqid();

function logear($Msg, $EsRespuesta) {
	global $uuid, $ActivarLOG, $debugOutput;

	if($ActivarLOG) {
		$debugOutput .= "<i>-----> Se envia log a /tmp/smsplus.log</i><br />";
		$flog = fopen("/tmp/smsplus.log","a");
		fwrite($flog,"\n---------------------------------------------------------\n");
		fwrite($flog,"  ".date('r',time())." - UUID: ".$uuid."\n");
		fwrite($flog,"---------------------------------------------------------\n");
		if($EsRespuesta)
			fwrite($flog,"<<<< ENVIADO <<<<\n");
		else
			fwrite($flog,">>>> RECIBIDO >>>>\n");
		fwrite($flog,$Msg);
		fclose($flog);
	}
}

$debugOutput = "<h1>Interfaz HTTP de SMS PLUS</h1>";

// Obtenemos el mensaje del server
if(!isset($_REQUEST['message']))
	die("No recibi el mensaje");
$Message = $_REQUEST['message'];
if($Message == "")
	die("Mensaje vacio");

logear($Message,false);				// Logeamos información recibida

// ¿Salida HTML (debug)?
if(isset($_REQUEST['debug']) && $_REQUEST['debug']==1)
	$Debug = true;
else {
	$Debug = false;
	// Eliminamos el reporte de errores de PHP
	error_reporting(0);
}

// Eliminamos secuencias de escape del mensaje
$Message = str_replace("\\","",$Message);

// Parseamos el XML
$parser_xml = new SMSPLUS_XMLProcessor($Message);
$parser_xml->Procesar();

// Mostramos información recibida (DEBUG)
$debugOutput .= "Mensaje recibido:<br /><ul>";
if($parser_xml->IsSMSPLUS)
	$debugOutput .= "<li>Es un mensaje SMS PLUS !";
else
	$debugOutput .= "<li>NO ES SMS PLUS !";
$debugOutput .= "<li>Tipo de mensaje: ".$parser_xml->MsgType." (".$parser_xml->MsgTypeID." - ".$parser_xml->MsgTypeName.")";
$debugOutput .= "<li>From: ".$parser_xml->FromURI;
$debugOutput .= "<li>From (telephone): ".$parser_xml->FromNumber;
$debugOutput .= "<li>From (alias): ".$parser_xml->FromAlias;
$debugOutput .= "<li>To (o Contacto): ".$parser_xml->ToURI;
if($parser_xml->AllowSMS)
	$debugOutput .= "<li>Envio de SMS permitido";
else
	$debugOutput .= "<li>No se permite el envio de mensajes SMS";
$debugOutput .= "<li>Clave SMS: ".$parser_xml->ClaveSMS;
$debugOutput .= "<li>Cuerpo del mensaje: ".$parser_xml->Body;
$debugOutput .= "<li>Historico de mensajes (mensaje inicial): ".$parser_xml->HistoricoMsgInicial;
$debugOutput .= "<li>Historico de mensajes (número de mensajes): ".$parser_xml->HistoricoNumMensajes;

$debugOutput .= "</ul>";

// XML de Respuesta
$xmlOutput = new SMSPLUS_XMLOutput($parser_xml);

// Kernel de procesado
$kernel = new SMSPLUSKernel($parser_xml,$xmlOutput,$debugOutput);
$kernel->Run();

// Enviamos salida de datos
if($Debug) {
	echo $kernel->Debug;
	echo "<br /><h2>ENTRADA XML</h2>";
	echo nl2br(htmlspecialchars($Message));
	echo "<br /><h2>SALIDA XML</h2>";
	echo nl2br(htmlspecialchars($kernel->Out->GetXML()));
} else {
	header("Content-type: text/xml");
	echo $kernel->Out->GetXML();
}

logear($kernel->Out->GetXML(), true);		// Logeamos información enviada

?>