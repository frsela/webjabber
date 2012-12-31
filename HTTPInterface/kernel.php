<?php

/***************************************************
 * Interfaz de comunicaciones HTTP para SMS PLUS
 * Fernando Rodríguez Sela, Telefónica I+D, 2008
 * SVN HeadURL: $HeadURL: file:///opt1/svn/widgets/trunk/SMSPlus/HTTPInterface/kernel.php $
 * SVN Id: $Id: kernel.php 45 2008-04-16 08:15:17Z frsela $
 ***************************************************/

class SMSPLUSKernel {
	var $In;
	var $Out;
	var $Debug;

	// Constructor
	function SMSPLUSKernel($XMLIn, $XMLOut, $Debug) {
		$this->In = $XMLIn;
		$this->Out = $XMLOut;
		$this->Debug = $Debug;
	}

	// Procesos HTTP->XMPP
	function ConectaHTTP2XMPP($Mensaje, $WaitResponse=false) {
		$Output = "";

		// Conectamos al conector HTTP <-> XMPP
		$fp = fsockopen("localhost", 10000, $errno, $errstr, 30);

		if (!$fp) {
			$this->Out->setTipoMensaje(52);
			$this->Out->setTexto("Error conectando al conector Jabber: $errstr ($errno)");
			$this->Debug .= "<br />Error conectando al conector Jabber: <b>$errstr ($errno)</b><br />";
			return -1;
		} else {
			// Esperamos respuesta servidor
			$in = fgets($fp, 19);
			if($in == "HTTP2XMPPConnector") {
				// Enviamos petición
				$out = $Mensaje."\r\n";
				fwrite($fp, $out);

				// Recibimos respuesta
				if($WaitResponse) {
					while (!feof($fp))
						$Output .= fgets($fp, 255);
				}
			}
			// Desconectamos
			fclose($fp);
		}

		return $Output;
	}

	function InicioSesion($FromURI) {
		if($this->ConectaHTTP2XMPP("CONNECT|".$FromURI) == -1)
			return false;
		else
			return true;
	}

	function CerrarSesion($FromURI) {
		if($this->ConectaHTTP2XMPP("DISCONNECT|".$FromURI) == -1)
			return false;
		else
			return true;
	}

	function EnvioMensaje($FromURI, $ToURI, $Body) {
		if($this->ConectaHTTP2XMPP("SEND|".$FromURI."|".$ToURI."|".$Body) == -1)
			return false;
		else
			return true;
	}

	function PullPorMensajes($FromURI) {
		$mensajesTXT = $this->ConectaHTTP2XMPP("RECEIVE|".$FromURI, true);
		if($mensajesTXT == -1)
			return false;
		else
			if($mensajesTXT != "")
				return explode("|",rtrim($mensajesTXT,"|"));
	}

	function PullPorPresencia($FromURI) {
		$contactosTXT = $this->ConectaHTTP2XMPP("RECEIVESTATUS|".$FromURI, true);
		if($contactosTXT == -1)
			return false;
		else
			if($contactosTXT != "")
				return explode("|",rtrim($contactosTXT,"|"));
	}

	// Procesos publicidad (MySQL)
	function GetPublicidad() {
		$PubliData = array();

		$mysqli = new mysqli("localhost", "smsplus", "smsplus", "SMSPlus");
		if (mysqli_connect_errno()) {
			$this->Out->setTipoMensaje(52);
			$this->Out->setTexto("Error conectando a la BBDD: ".mysqli_connect_error());
			$this->Debug .= "<br />Error conectando a la BBDD: <b>".mysqli_connect_error()."</b><br />";
			return false;
		}
		$this->Debug .= "Host information: ".$mysqli->host_info;

		if ($result = $mysqli->query("SELECT Banner, URL, Texto FROM Publicidad ORDER BY RAND(". time() . " * " . time() . ") LIMIT 1", MYSQLI_USE_RESULT)) {
			$data = $result->fetch_object();

			$PubliData['Banner'] = $data->Banner;
			$PubliData['URL'] = $data->URL;
			$PubliData['Texto'] = $data->Texto;

			$result->close();
		} else {
			$this->Debug .= "Error consultando";
		}

		$mysqli->close();

		return $PubliData;
	}

	function GetHistorico($From,$To,$Inicial,$NumMensajes) {
		$Mensajes = array();

		$mysqli = new mysqli("localhost", "smsplus", "smsplus", "SMSPlus");
		if (mysqli_connect_errno()) {
			$this->Out->setTipoMensaje(52);
			$this->Out->setTexto("Error conectando a la BBDD: ".mysqli_connect_error());
			$this->Debug .= "<br />Error conectando a la BBDD: <b>".mysqli_connect_error()."</b><br />";
			return false;
		}
		$this->Debug .= "Host information: ".$mysqli->host_info;

		if ($result = $mysqli->query("SELECT DISTINCT DATE(`Timestamp`) AS Fecha, TIME(`Timestamp`) AS Hora, `From`, `To`, Mensaje FROM HistoricoMensajes WHERE (`From`=\"$From\" AND `To`=\"$To\") OR (`From`=\"$To\" AND `To`=\"$From\") ORDER BY `Timestamp` DESC LIMIT $Inicial,$NumMensajes", MYSQLI_USE_RESULT)) {
			while($data = $result->fetch_object()) {
				$aux = array();
	
				$aux['Fecha'] = $data->Fecha;
				$aux['Hora'] = $data->Hora;
				$aux['Origen'] = $data->From;
				$aux['Destino'] = $data->To;
				$aux['Mensaje'] = $data->Mensaje;
	
				$Mensajes[] = $aux;
			}
			$result->close();
		} else {
			$this->Debug .= "Error consultando";
		}

		$mysqli->close();

		return $Mensajes;
	}

	// Envio de mensajes SMS a través de la API de Open Movilforum
	function SendSMS($From, $To, $SMS) {
		$aux = explode("@",$From);
		$tm_login = $aux[0];
		$tm_password = $tm_login;	// La password es el número de teléfono para la demo
		$aux = explode("@",$To);
		$tm_to = $aux[0];
		$tm_mensaje = $SMS." - Mensaje enviado desde SMS PLUS de movistar";

		# variables post
		$host="opensms.movistar.es";
		$service_uri="/aplicacionpost/loginEnvio.jsp";
		$vars="TM_ACTION=AUTHENTICATE&TM_LOGIN=".$tm_login."&TM_PASSWORD=".$tm_password."&to=".$tm_to."&message=".$tm_mensaje;

		# cabecera http HTTP
		$header = "Host: $host\r\n";
		$header .= "User-Agent: SMS-PLUS PHP Script\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: ".strlen($vars)."\r\n";
		$header .= "Connection: close\r\n\r\n";

		$fp = pfsockopen($host, 443, $errno, $errstr);

		if (!$fp) {
			$this->Debug .= "<br />".$errstr." ($errno)\n";
			$this->Debug .= "<br />".$fp;
			return false;
		} else {
			fputs($fp, "POST $service_uri HTTP/1.1\r\n");
			fputs($fp, $header.$vars);
			fwrite($fp, $out);
			// Mostramos la salida en debug
			$this->Debug .= "<br />Salida API SMS Open MovilForum:<br />";
			while (!feof($fp)) {
				$this->Debug .= fgets($fp, 128);
			}
			fclose($fp);
			return true;
		}
	}

	// Recepción de contactos de la agenda a través de la API de Copiagenda de Open Movilforum
	function GetContacts($From) {
		$aux = explode("@",$From);
		$tm_login = $aux[0];
		$tm_password = $tm_login;

		$lista_contactos = "";
		$error_conexion = false;

		$ch = curl_init();
		$url = "https://copiagenda.movistar.es/cp/ps/Main/login/Agenda";
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt ($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,false);
		
		$useragent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; .NET CLR 2.0.50727)";
		curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		
		# Iniciamos login con HTTPS
		$res= curl_setopt ($ch, CURLOPT_URL,$url);
		$postdata = "TM_ACTION=LOGIN&TM_LOGIN=$tm_login&TM_PASSWORD=$tm_password";
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($ch, CURLOPT_POST, true);
		# cabeceras HTTP
		$header = array("Content-Type: application/x-www-form-urlencoded",
				"Content-Length: ".strlen($postdata),
				"Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, */*",
				"Connection: Keep-Alive");
		curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
		$result = curl_exec ($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		
		if (curl_errno($ch)) {
			$this->Debug .= "<br />Error: " . curl_error($ch);
			$error_conexion = true;
		} else {
			if ($code == 301 || $code == 302) {
				# Nos redirecionan y nos dan una cookie
				list($header, $result) = explode("\n\n", $result, 2);
				
				$matches = array();
				preg_match('/Location:(.*?)\n/', $header, $matches);
				$url = @parse_url(trim(array_pop($matches)));
				
				$new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
			
				$matches2 = array();
				preg_match('/Set-Cookie: s=(.*?)\n/', $header, $matches2);
				list($cookie_value2, $rest) = explode("; ", $matches2[1], 2);
				
				$matches = array();
				preg_match('/Set-Cookie:skf=(.*?)\n/', $header, $matches);
				list($cookie_value1, $rest) = explode("; ", $matches[1], 2);
			
				curl_setopt($ch, CURLOPT_URL, $new_url);
				# cabeceras HTTP
				$header = array("Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, */*",
						"Cookie: skf=$cookie_value; s=$cookie_value2",
						"Connection: Keep-Alive");
				curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
				curl_setopt($ch, CURLOPT_GET, true);
				$result = curl_exec ($ch);
				
				$matches = array();
				preg_match('/password" value=(.*?)>\n/', $result, $matches);
				$password = $matches[1];
			
				# Nos piden que nos re-autentiquemos con los datos de usuario + la cookie y nos devuelven un token de sesion
				curl_setopt($ch, CURLOPT_URL, "https://copiagenda.movistar.es/cp/ps/Main/login/Authenticate");
				$postdata = "password=$password&u=$tm_login&d=movistar.es";
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_GET, false);
				# cabeceras HTTP
				$header = array("Content-Type: application/x-www-form-urlencoded",
						"Content-Length: ".strlen($postdata),
						"Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, */*",
						"Cookie: skf=$cookie_value1; s=$cookie_value2",
						"Connection: Keep-Alive");
				$result = curl_exec ($ch);
				$matches = array();
				preg_match('/&t=(.*?)"\n/', $result, $matches);
				$token = $matches[1];
			
				# Pedimos un exportado de los datos en fichero txt separado por tabuladores
				$urlfinal = "https://copiagenda.movistar.es/cp/ps/PSPab/preferences/ExportContacts?d=movistar.es&c=yes&u=$tm_login&t=$token";
				curl_setopt($ch, CURLOPT_URL, $urlfinal);
				$postdata = "fileFormat=TEXT&charset=8859_1&delimiter=TAB";
				curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_GET, false);
				# cabeceras HTTP
				$header = array("Content-Type: application/x-www-form-urlencoded",
						"Content-Length: ".strlen($postdata),
						"Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, */*",
						"Cookie: skf=$cookie_value1; s=$cookie_value2",
						"Connection: Keep-Alive");
				# En este caso nos interesa eliminar las cabeceras
				curl_setopt ($ch, CURLOPT_HEADER, false);
				$result = curl_exec ($ch);
				$lista_contactos = $result;
			} else {
				$this->Debug .= "<br />No redirigido";
			}

			curl_close($ch);
		}

		$this->Debug .= "<br /><h2>Informacion devuelta por COPIAGENDA</h2><br />".$lista_contactos;

		// Procesamos salida - La primera línea son las cabeceras y el resto los contactos
		$this->Debug .= "<br /><h2>Informacion procesada de COPIAGENDA</h2><br /><table border=\"1\">";

		// Eliminamos las comillas de toda la respuesta y separamos las líneas
		$lineas = explode("\n", str_replace("\"","",$lista_contactos));
		foreach($lineas AS $linea) {
			// Limpiamos información recibida ya que añade un pequeño javascript para "controlar MSISDN" Antes de esto, deja bastantes líneas en blanco, así que a la primera línea vacía, terminamos
			if($linea == "")
				break;

			$this->Debug .= "<tr>";
			$datos[] = explode("\t",$linea);
			foreach($datos[count($datos)-1] AS $dato) {
				$this->Debug .= "<td>".$dato."</td>";
			}
			$this->Debug .= "</tr>";
		}
		$this->Debug .= "</table>";

		// Devolvemos array de contactos
		if(!$error_conexion)
			return $datos;
		else
			return false;
	}

	// Ejecutar procesado
	function Run() {
		// Procesamos mensaje
		if($this->In->IsSMSPLUS) {
			switch($this->In->MsgType) {
				case ND:
					$this->Out->setTipoMensaje(52);
					$this->Out->setTexto("Tipo de mensaje no definido");
					$this->Debug .= "<br /><b>Tipo de mensaje no definido</b><br />";
					break;
				case ALTASERVICIO:
					$this->Out->setTipoMensaje(51);
					$this->Debug .= "<br /><b>Pendiente de implementar</b><br />";
					break;
				case INICIOSESION:
					if(!$this->InicioSesion($this->In->FromURI))
						break;
					$this->Out->setTipoMensaje(53);
					$contactos = $this->GetContacts($this->In->FromURI);
					$first=true;
					foreach($contactos AS $contacto) {
						if($first)	// La primera fila, la rechazamos (cabeceras)
							$first=false;
						else {
							// Copiagenda devuelve los números de teléfono en las columnas 11,13,14,16 y 18
							//  utilizamos el primero que nos encontremos.
							$cols = array(11,13,14,16,18);
							foreach ($cols as $aux)
								if($contacto[$aux] != "") {
									$NumTelefono = $contacto[$aux];
									break;
								}

							// ¿Hay foto en el servidor?
							if(file_exists("fotos/".$NumTelefono.".jpg"))
								$foto = "http://smsplus.handsets.es/fotos/".$NumTelefono.".jpg";
							else
								$foto = "";

							$this->Out->OpenNodo("Contacto");
							$this->Out->AddNodo("Item","","nombre=\"".$contacto[1]." ".$contacto[2]."\" apellido=\"".$contacto[3]."\" alias=\"".$contacto[4]."\" msisdn=\"".$NumTelefono."@handsets.es\" foto=\"".$foto."\"");
							$this->Out->CloseNodo("Contacto");
						}
					}
					break;
				case ACTUALIZARDATOS:
					$this->Out->setTipoMensaje(51);
					$this->Debug .= "<br /><b>Pendiente de implementar</b><br />";
					break;
				case ENVIOMENSAJE:
					// Comprobamos si el contacto está ONLINE o no
					$contactos = $this->PullPorPresencia($this->In->FromURI);
					if($contactos == false)
						break;

					$this->Out->setTipoMensaje(54);

					// Procesamos información recibida
					foreach($contactos AS $c) {
						$aux = explode("#",$c);
						$aux2 = explode("@",$aux[0]);
						if($aux[0] == $this->In->ToURI) {
							$this->Debug .= "<br>$aux[0]";
							if($aux[1] == "online") {
								$this->Debug .= "<br>ONLINE";
								$this->EnvioMensaje($this->In->FromURI, $this->In->ToURI, $this->In->Body);
								$this->Out->AddNodo("SMS","","envio=\"no\"");
							} else {
								// Offline => SMS
								$this->Debug .= "<br>OFFLINE - INTENTAMOS SMS";

								// Sólo enviamos los "teléfonos"
								if($aux2[1] == "handsets.es") {
									$this->Debug .= "<br>es un teléfono - OK (SMS)";
									// ¿Están permitidos los SMS?
									if($this->In->AllowSMS) {
										$this->Debug .= "<br>SMS permitido";
										$this->SendSMS($this->In->FromURI, $this->In->ToURI, $this->In->Body);
										$this->Out->AddNodo("SMS","","envio=\"si\"");
									}
								} else {
									$this->Debug .= "<br>NO es un teléfono - Se envia por JABBER";
									$this->EnvioMensaje($this->In->FromURI, $this->In->ToURI, $this->In->Body);
									$this->Out->AddNodo("SMS","","envio=\"no\"");
								}
							}
						}
					}
					break;
				case PULLPORMENSAJES:
					$ListaMensajes = $this->PullPorMensajes($this->In->FromURI);
					if($ListaMensajes == false)
						break;
					$this->Out->setTipoMensaje(55);
					foreach($ListaMensajes AS $MSG) {
						$this->Debug .= "<br />Mensaje: ".$MSG;
						$mess = explode("#",trim($MSG));
						$JID = explode("@",$mess[2]);
						$this->Out->OpenNodo("IM");
						$this->Out->AddNodo("Origen","","valor=\"".$mess[2]."\" alias=\"".$JID[0]."\"");
						$this->Out->AddNodo("Texto",$mess[3],"fecha=\"".$mess[0]."\" hora=\"".$mess[1]."\"");
						$this->Out->CloseNodo("IM");
					}
					break;
				case PULLPORPUBLICIDAD:
					$PubliData = $this->GetPublicidad();
					if($PubliData == false)
						break;
					$this->Out->setTipoMensaje(56);
					$this->Debug .= "<br>Banner.: ".$PubliData['Banner']."<br/>";
					$this->Debug .= "URL....: ".$PubliData['URL']."<br/>";
					$this->Debug .= "Texto..: ".$PubliData['Texto']."<br/>";
					$this->Out->AddNodo("Anuncio",$PubliData['Texto'],"banner=\"".$PubliData['Banner']."\" url=\"".$PubliData['URL']."\"");
					break;
				case PULLPORPRESENCIACONTACTOS:
					$contactos = $this->PullPorPresencia($this->In->FromURI);
					if($contactos == false)
						break;
					$this->Out->setTipoMensaje(58);

					// Procesamos información recibida
					$MSISDN_Online = array();
					foreach($contactos AS $c) {
						$this->Debug .= "<br>$c";
						$aux = explode("#",$c);
						if($aux[1] == "online") {
							// Sólo enviamos los "teléfonos"
							$aux2 = explode("@",$aux[0]);
							if($aux2[1] == "handsets.es")
								$MSISDN_Online[] = $aux[0];
						}
					}

					// Generamos mensaje de salida
					$this->Out->AddNodo("Entradas","","numero=\"".count($MSISDN_Online)."\"");
					$this->Out->OpenNodo("Contactos");
					foreach($MSISDN_Online AS $MSISDN)
						$this->Out->AddNodo("Item","","msisdn=\"".$MSISDN."\"");
					$this->Out->CloseNodo("Contactos");
					break;
				case PULLPORHISTORICOCONVERSACION:
					$Mensajes = $this->GetHistorico($this->In->FromURI,$this->In->ToURI,$this->In->HistoricoMsgInicial,$this->In->HistoricoNumMensajes);
					$this->Out->setTipoMensaje(57);
					if($Mensajes == false) {
						$this->Out->AddNodo("Conversacion","","inicial=\"".$this->In->HistoricoMsgInicial."\" numero=\"0\"");
						$this->Out->AddNodo("Contacto","","valor=\"".$this->In->ToURI."\"");
					} else {
						$this->Out->AddNodo("Conversacion","","inicial=\"".$this->In->HistoricoMsgInicial."\" numero=\"".count($Mensajes)."\"");
						$this->Out->AddNodo("Contacto","","valor=\"".$this->In->ToURI."\"");
						for($i=0; $i<count($Mensajes);$i++) {
							$this->Out->OpenNodo("IM");
							if($this->In->FromURI == $Mensajes[$i]['Origen'])
								$Tipo = "enviado";
							else
								$Tipo = "recibido";
							$this->Out->AddNodo("Texto",$Mensajes[$i]['Mensaje'],"tipo=\"".$Tipo."\" fecha=\"".$Mensajes[$i]['Fecha']."\" hora=\"".$Mensajes[$i]['Hora']."\"");
							$this->Out->CloseNodo("IM");
						}
					}
					break;
				case FINALSESION:
					$this->Debug .= "<br />Cierre de sesión de ".$this->In->FromURI."<br />";
					if(!$this->CerrarSesion($this->In->FromURI))
						break;
					$this->Out->setTipoMensaje(51);
					break;
				default:
					$this->Out->setTipoMensaje(52);
					$this->Out->setTexto("Tipo de mensaje no reconocido");
					$this->Debug .= "<br /><b>Tipo de mensaje no reconocido</b><br />";
			}
		} else {
			$this->Out->setTipoMensaje(52);
			$this->Out->setTexto("No es un mensaje correcto");
			$this->Debug .= "<br /><b>No es un mensaje correcto</b><br />";
		}
	}
}