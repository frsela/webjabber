<?php

/***************************************************
 * Interfaz de comunicaciones HTTP para SMS PLUS
 * Fernando Rodríguez Sela, Telefónica I+D, 2008
 * SVN HeadURL: $HeadURL: file:///opt1/svn/widgets/trunk/SMSPlus/HTTPInterface/xmlprocessor.php $
 * SVN Id: $Id: xmlprocessor.php 26 2008-03-27 08:39:25Z frsela $
 ***************************************************/

define("ND",					-1);
define("ALTASERVICIO",				1);
define("INICIOSESION",				2);
define("ACTUALIZARDATOS",			3);
define("ENVIOMENSAJE",				4);
define("PULLPORMENSAJES",			5);
define("PULLPORPUBLICIDAD",			6);
define("PULLPORPRESENCIACONTACTOS",		7);
define("PULLPORHISTORICOCONVERSACION",		8);
define("FINALSESION",				9);

class SMSPLUS_XMLProcessor {
	// Atributos genéricos
	var $XMLParser;
	var $XMLData;

	// Datos recogidos del mensaje
	var $IsSMSPLUS;
	var $MsgTypeName;
	var $MsgTypeID;
	var $MsgType;
	var $FromURI;
	var $ToURI;
	var $FromNumber;
	var $FromAlias;
	var $AllowSMS;
	var $ClaveSMS;
	var $HistoricoMsgInicial;
	var $HistoricoNumMensajes;

	var $InBody;
	var $Body;

	// Constructor
	function SMSPLUS_XMLProcessor($Data) {
		$this->XMLData = $Data;

		$this->MsgTypeName = "Unknown";
		$this->MsgTypeID = "-1";

		$this->IsSMSPLUS = false;
		$this->MsgType = ND;
		$this->FromURI = "";
		$this->ToURI = "";
		$this->FromNumber = "";
		$this->FromAlias = "";
		$this->AllowSMS = false;
		$this->ClaveSMS = "";
		$this->HistoricoMsgInicial = 0;
		$this->HistoricoNumMensajes = 0;

		$this->InBody = false;
		$this->Body = "";
	}

	// Método de procesado
	function Procesar() {
		$this->XMLParser = xml_parser_create();
		xml_set_object($this->XMLParser, $this);
		xml_set_element_handler($this->XMLParser, "startTag", "endTag");
		xml_set_character_data_handler($this->XMLParser, "cdata");
	
		$data = xml_parse($this->XMLParser,$this->XMLData,true);
		if(!$data) {
			die(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($this->XMLParser)),
			xml_get_current_line_number($this->XMLParser)));
		}
	
		xml_parser_free($this->XMLParser);
	}

	function startTag($parser, $name, $attrs) {
		switch($name) {
			case "SMSPLUS":
				$this->IsSMSPLUS = true;
				break;
			case "MENSAJE":
				$this->MsgTypeName = $attrs['TIPO'];
				$this->MsgTypeID = $attrs['ID'];
				switch($attrs['TIPO']) {
				case "AltaServicio":
					$this->MsgType = ALTASERVICIO;
					break;
				case "InicioSesion":
					$this->MsgType = INICIOSESION;
					break;
				case "ActualizarDatos":
					$this->MsgType = ACTUALIZARDATOS;
					break;
				case "EnvioMensaje":
					$this->MsgType = ENVIOMENSAJE;
					break;
				case "PullPorMensajes":
					$this->MsgType = PULLPORMENSAJES;
					break;
				case "PullPorPublicidad":
					$this->MsgType = PULLPORPUBLICIDAD;
					break;
				case "PullPorPresenciaContactos":
					$this->MsgType = PULLPORPRESENCIACONTACTOS;
					break;
				case "PullPorHistoricoConversacion":
					$this->MsgType = PULLPORHISTORICOCONVERSACION;
					break;
				case "FinalSesion":
					$this->MsgType = FINALSESION;
					break;
				default:
					$this->MsgType = ND;
				}
				break;
			case "USUARIO":
				$this->FromURI = $attrs['VALOR'];
				break;
			case "NUMEROMOVIL":
				$this->FromNumber = $attrs['VALOR'];
				break;
			case "ALIAS":
				$this->FromAlias = $attrs['VALOR'];
				break;
			case "DESTINATARIO":
				$this->ToURI = $attrs['VALOR'];
				break;
			case "SMSPERMITIDO":
				if($attrs['VALOR'] == "si")
					$this->AllowSMS = true;
				else
					$this->AllowSMS = false;
				break;
			case "CLAVESMS":
				$this->ClaveSMS = $attrs['VALOR'];
				break;
			case "CONTACTO":
				// Es el contacto en la solicitud de mensajes históricos, se puede reutilizar el campo de "destinatario" ;)
				$this->ToURI = $attrs['VALOR'];
				break;
			case "CONVERSACION":
				// Parámetros de solicitud de mensajes históricos
				$this->HistoricoMsgInicial = $attrs['INICIAL'];
				$this->HistoricoNumMensajes = $attrs['NUMERO'];
				break;
			case "TEXTO":
				$this->InBody = true;
				break;
			default:
				// Etiqueta no reconocida
				print "<br />Etiqueta no reconocida: ".$name;
		}
	}
	
	function endTag($parser, $name) {
		switch($name) {
			case "SMSPLUS":
			case "MENSAJE":
			case "USUARIO":
			case "NUMEROMOVIL":
			case "ALIAS":
			case "DESTINATARIO":
			case "SMSPERMITIDO":
			case "CLAVESMS":
			case "CONTACTO":
			case "CONVERSACION":
				break;
			case "TEXTO":
				$this->InBody = false;
				break;
			default:
				// Etiqueta no reconocida
				print "<br />Cierre de etiqueta no reconocida: ".$name;
		}
	}
	
	function cdata($parser, $cdata) {
		if($this->InBody)
			$this->Body = $cdata;
	}
}

class SMSPLUS_XMLOutput {
	var $XMLInput;
	var $lines;
	var $tipo;

	// Constructor
	function SMSPLUS_XMLOutput($XMLInput) {
		$this->XMLInput = $XMLInput;
		$this->lines = array();
		$this->tipo = -1;
	}

	// Asignamos tipo de mensaje
	function setTipoMensaje($idTipo) {
		$this->tipo = $idTipo;

		switch($this->tipo) {
		case 51:
			$this->setMensaje("OK",$this->tipo);
			$this->setMensajeOrigen();
			break;
		case 52:
			$this->setMensaje("Error",$this->tipo);
			$this->setMensajeOrigen();
			break;
		case 53:
			$this->setMensaje("AgendaContactos",$this->tipo);
			break;
		case 54:
			$this->setMensaje("MensajeProcesado",$this->tipo);
			break;
		case 55:
			$this->setMensaje("MensajesRecibidos",$this->tipo);
			break;
		case 56:
			$this->setMensaje("Publicidad",$this->tipo);
			break;
		case 57:
			$this->setMensaje("HistoricoConversacion",$this->tipo);
			break;
		case 58:
			$this->setMensaje("PresenciaContactos",$this->tipo);
			break;
		default:
			$this->setMensaje("Error",$this->tipo);
			$this->setMensajeOrigen();
			$this->setTexto("Tipo de mensaje de salida NO RECONOCIDO");
		}
	}

	// Nodo tipo de mensaje
	function setMensaje($Tipo,$Id) {
		$this->AddNodo("Mensaje","","tipo=\"".$Tipo."\" id=\"".$Id."\"");
	}

	function setMensajeOrigen() {
		$this->AddNodo("MensajeOrigen","","tipo=\"".$this->XMLInput->MsgTypeName."\" id=\"".$this->XMLInput->MsgTypeID."\"");
	}

	// Nodo Texto
	function setTexto($Texto) {
		$this->AddNodo("Texto",$Texto);
	}

	// Agregamos nodo
	function AddNodo($Tipo,$Value,$Atributos = "") {
		if($Atributos != "")
			$Attr = " ".$Atributos;
		if($Value == "")
			$this->lines[] = "<".$Tipo.$Attr." />";
		else
			$this->lines[] = "<".$Tipo.$Attr.">".$Value."</".$Tipo.">";
	}

	// Para nodos largos
	function OpenNodo($Tipo,$Atributos = "") {
		if($Atributos != "")
			$Attr = " ".$Atributos;
		$this->lines[] = "<".$Tipo.$Attr.">";
	}

	function CloseNodo($Tipo) {
		$this->lines[] = "</".$Tipo.">";
	}

	// Generación del XML de salida
	function GetXML() {
		$xmlOutput = "<SMSPLUS>\n";

		foreach($this->lines as $line)
			$xmlOutput .= "\t".$line."\n";

		$xmlOutput .= "</SMSPLUS>\n";

		return $xmlOutput;
	}
}

?>