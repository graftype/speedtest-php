from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
import sys,time
class Handler(BaseHTTPRequestHandler):
 def log_message(self,*args):pass
 def do_GET(self):
  from urllib.parse import urlsplit
  path=urlsplit(self.path).path
  if path.endswith('/latency.txt'):
   data=b'test=test';typ='text/plain'
  elif '/random' in path and path.endswith('.jpg'):
   data=b'\xff\xd8'+b'x'*262144;typ='image/jpeg'
   if '/slow/' in path: time.sleep(.05)
  else:
   self.send_response(404);self.end_headers();return
  if '/failed/' in path:
   self.send_response(503);self.end_headers();return
  if '/html/' in path:data=b'<html>Denied</html>';typ='text/html'
  self.send_response(200);self.send_header('Content-Type',typ);self.send_header('Content-Length',len(data));self.end_headers();self.wfile.write(data)
 def do_POST(self):
  data=self.rfile.read(int(self.headers.get('Content-Length',0)))
  if '/failed/' in self.path:self.send_response(403);self.end_headers();return
  self.send_response(200);self.send_header('Content-Type','text/plain');self.end_headers();self.wfile.write(('size='+str(len(data))).encode())
s=ThreadingHTTPServer(('127.0.0.1',0),Handler)
open(sys.argv[1],'w').write(str(s.server_port));s.serve_forever()
