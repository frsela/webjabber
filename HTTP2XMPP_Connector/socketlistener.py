#!/usr/bin/python
# -*- coding: utf-8 -*-
# HTTP to XMPP Connector - Socket Listener
# Fernando Rodríguez Sela, 2008
# SVN HeadURL: $HeadURL: file:///opt1/svn/widgets/trunk/SMSPlus/HTTP2XMPP_Connector/socketlistener.py $
# SVN Id: $Id: socketlistener.py 5 2008-03-10 14:26:39Z frsela $

from socket import *

class SocketListener:
	def __init__(self, TCPPort):
		# Abrimos socket NO BLOQUEANTE
		self.serversock = socket(AF_INET, SOCK_STREAM)
		self.serversock.bind(("0.0.0.0",TCPPort))
		self.serversock.setblocking(0)
		self.serversock.listen(1)

	def __del__(self):
		self.Desconectar()

	def RecibirDatos(self):
		try:
			self.cliente, self.direccion = self.serversock.accept()
			print 'Conexion recibida desde: ', self.direccion
			self.EnviarDatos("HTTP2XMPPConnector\r\n")
			buff = self.cliente.recv(1024)
			return buff
		except:
			return "NODATA"

	def EnviarDatos(self, Data):
		self.cliente.send(Data)

	def DesconectarCliente(self):
		self.cliente.close()

	def Desconectar(self):
		self.serversock.close()
