#!/usr/bin/python
# -*- coding: utf-8 -*-
# HTTP to XMPP Connector - MAIN
# Fernando Rodríguez Sela, 2008
# SVN HeadURL: $HeadURL: file:///opt1/svn/widgets/trunk/SMSPlus/HTTP2XMPP_Connector/main.py $
# SVN Id: $Id: main.py 23 2008-03-26 08:08:08Z frsela $

import sys
import xmppclient
import socketlistener

class Main:
	# Constructor
	def __init__(self):
		print "Bienvenido al conector HTTP <-> XMPP (Telefonica I+D, 2008)\r\n"
	
		# Analizamos parámetros de entrada
		if( len(sys.argv) != 2 ):
			print "Debe especificar el puerto de escucha TCP !"
			print " Uso: %s <TCP Port>\r\n"%sys.argv[0]
			self.Status = -1
		else:
			self.TCPPort = int(sys.argv[1])
			self.Status = 1

	def Start(self):
		# Creamos diccionario de clientes JABBER
		self.JabberClients = dict()
	
		# Abrimos socket
		self.Server = socketlistener.SocketListener(self.TCPPort)
		print "Escuchando en el puerto TCP %d"%self.TCPPort
	
		# Iniciamos proceso iterativo de control
		while self.StartConnector():
			pass
	
		# Finalizamos
		print "Desconectando socket ...\r\n"
		self.Server.Desconectar()

		#print "Desconectando clientes ...\r\n"
		#for c in self.JabberClients.values():
		#	c.Desconectar()

		print "Conector finalizado correctamente\r\n"

	def StartConnector(self):
		try:
			# Procesamos tareas pendientes de todos los clientes XMPP iniciados
			for cliente in self.JabberClients.values():
				cliente.ProcesarDatos()

			# Comprobamos si tenemos algún comando pendiente por hacer
			Comando = self.Server.RecibirDatos()
			if Comando != "NODATA":
				cmd = Comando[0:-2].split("|")
				if(cmd[0] == "CONNECT"):
					print "Mensaje de conexion\r\n";
					JID = cmd[1]
					# Si no existe el objeto, lo creamos
					if(self.JabberClients.keys().count(JID) == 0):
						self.JabberClients[JID] = xmppclient.XMPPClient()
					# Conectamos al servidor de JABBER
					self.JabberClients[JID].Conectar(JID)
				elif(cmd[0] == "DISCONNECT"):
					print "Mensaje de desconexion\r\n";
					JID = cmd[1]
					#self.JabberClients[JID].Desconectar()	# Desconectamos
					self.JabberClients.pop(JID)		# Destruimos objeto
				elif(cmd[0] == "SEND"):
					print "Mensaje de envio\r\n";
					From, To, Msg = cmd[1], cmd[2], cmd[3]
					if(self.JabberClients.keys().count(From) == 1):
						self.JabberClients[From].EnviarMensaje(To,Msg)
				elif(cmd[0] == "RECEIVE"):
					print "Mensaje de recepcion\r\n";
					From = cmd[1]
					if(self.JabberClients.keys().count(From) == 1):
						Msg = self.JabberClients[From].RecibirMensajes()
						for m in Msg:
							self.Server.EnviarDatos(m)
							self.Server.EnviarDatos("|")
				elif(cmd[0] == "RECEIVESTATUS"):
					print "Mensaje de recepcion de estados de presencia\r\n";
					From = cmd[1]
					if(self.JabberClients.keys().count(From) == 1):
						Msg = self.JabberClients[From].RecibirPresencia()
						self.Server.EnviarDatos(Msg)
				elif(cmd[0] == "DUMPRCV"):
					print "Mensaje de depuracion (DUMP RCV)\r\n";
					print xmppclient.RcvMsg

				# Si estamos con conexión abierta, la cerramos
				self.Server.DesconectarCliente()

		except KeyboardInterrupt: return 0
		return 1

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