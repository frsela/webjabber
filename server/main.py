#!/usr/bin/python
# -*- coding: utf-8 -*-
# HTTP to XMPP Connector - MAIN
# Fernando Rodríguez Sela, 2012

import sys
import xmppclient
import BaseHTTPServer
import urlparse
import threading

class HTTPJabberServer(BaseHTTPServer.BaseHTTPRequestHandler):
	@staticmethod
	def startJabber():
		# Creamos diccionario de clientes JABBER
		print "Inicializando diccionario de clientes Jabber"
		HTTPJabberServer.JabberClients = dict()
		# Procesado periódico
		HTTPJabberServer.processJabber()

	@staticmethod
	def stopJabber():
		print "Desconectando clientes ...\r\n"
		for c in HTTPJabberServer.JabberClients.values():
			c.Desconectar()

	# Procesamos tareas pendientes de todos los clientes XMPP iniciados
	@staticmethod
	def processJabber():
		for cliente in HTTPJabberServer.JabberClients.values():
			cliente.ProcesarDatos()
		threading.Timer(1,HTTPJabberServer.processJabber).start()

	# Procesamos peticiones HTTP
	def do_GET(self):
		message = ""

		# Analizamos la petición
		parsed_path = urlparse.urlparse(self.path)
		params = urlparse.parse_qs(parsed_path.query)

		try:
			if parsed_path.path == "/connect":
				JID = params['jid'][0]
				passwd = params['pwd'][0]
				# Si no existe el objeto, lo creamos
				if(HTTPJabberServer.JabberClients.keys().count(JID) == 0):
					HTTPJabberServer.JabberClients[JID] = xmppclient.XMPPClient()
				# Conectamos al servidor de JABBER
				HTTPJabberServer.JabberClients[JID].Conectar(JID,passwd)

			elif parsed_path.path == "/disconnect":
				JID = params['jid'][0]
				HTTPJabberServer.JabberClients[JID].Desconectar()	# Desconectamos
				HTTPJabberServer.JabberClients.pop(JID)				# Destruimos objeto

			elif parsed_path.path == "/send":
				From, To, Msg = params['from'][0], params['to'][0], params['msg'][0]
				if(HTTPJabberServer.JabberClients.keys().count(From) == 1):
					HTTPJabberServer.JabberClients[From].EnviarMensaje(To,Msg)

			elif parsed_path.path == "/receive":
				JID = params['jid'][0]
				if(HTTPJabberServer.JabberClients.keys().count(JID) == 1):
					Msg = HTTPJabberServer.JabberClients[JID].RecibirMensajes()
					message = "{ messages: ["
					first = True
					for m in Msg:
						if first:
							first = False
						else:
							message += ","
						message += m
					message += "]}"

			elif parsed_path.path == "/receivestatus":
				JID = params['jid'][0]
				if(HTTPJabberServer.JabberClients.keys().count(JID) == 1):
					message = HTTPJabberServer.JabberClients[JID].RecibirPresencia()

			elif parsed_path.path == "/dumprcv":
				message = xmppclient.RcvMsg
				print message

			elif parsed_path.path == "/help":
				message = '\n'.join([
					'/connect?jid&pwd',
					'/disconnect?jid',
					'/send?from&to&msg',
					'/receive?jid',
					'/receivestatus?jid',
					'/dumprcv',
					'/debug',
				])

			elif parsed_path.path == "/debug":
				message = '\n'.join([
					'CLIENT VALUES:',
					'client_address=%s (%s)' % (self.client_address,
												self.address_string()),
					'command=%s' % self.command,
					'path=%s' % self.path,
					'real path=%s' % parsed_path.path,
					'query=%s' % parsed_path.query,
					'request_version=%s' % self.request_version,
					'',
					'SERVER VALUES:',
					'server_version=%s' % self.server_version,
					'sys_version=%s' % self.sys_version,
					'protocol_version=%s' % self.protocol_version,
					'',
					])

			self.send_response(200)
			self.send_header('Access-Control-Allow-Origin','*')
			self.end_headers()
			self.wfile.write(message)

		except:
			self.send_response(500)
			self.send_header('Access-Control-Allow-Origin','*')
			self.end_headers()
			self.wfile.write("Error parsing request")

		return

class Main:
	# Constructor
	def __init__(self):
		print "Bienvenido al conector HTTP <-> XMPP (Telefonica I+D, 2008-Telefonica Digital, 2012)\r\n"
	
		# Analizamos parámetros de entrada
		if( len(sys.argv) != 2 ):
			print "Debe especificar el puerto de escucha TCP !"
			print " Uso: %s <TCP Port>\r\n"%sys.argv[0]
			self.Status = -1
		else:
			self.TCPPort = int(sys.argv[1])
			self.Status = 1

	def Start(self):
		HTTPJabberServer.startJabber()

		# Abrimos socket
		self.httpd = BaseHTTPServer.HTTPServer(
			('',self.TCPPort),
			HTTPJabberServer
		)
		print "Escuchando en el puerto TCP %d"%self.TCPPort
		self.httpd.serve_forever()
	
		# Finalizamos
		HTTPJabberServer.stopJabber()
		print "Conector finalizado correctamente\r\n"

# Lanzamos programa
if __name__ == "__main__":
	m = Main()
	if(m.Status != -1):
		m.Start()
else:
	print "Esta arrancando desde el interprete de PYTHON, para lanzar la aplicacion:"
	print " 1.- Construir un objeto de la clase Main(): m = Main()"
	print " 2.- El constructor comprobara los parámetros de entrada, como no se le pueden pasar, debera especificar el puerto de escucha"
	print " 3.- Especifique el puerto de escucha: m.TCPPort = <Puerto que quiera>"
	print " 4.- Arranque la aplicacion: m.Start()"
