from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlsplit
import sys,time
class Handler(BaseHTTPRequestHandler):
    def log_message(self,*args): pass
    def handle_request(self):
        if self.headers.get('X-Tls-Key')!='fixture-key' or self.headers.get('X-Tls-Proxy')!='http://user:secret@proxy.invalid:8080' or self.headers.get('X-Tls-Follow-Redirects')!='false':
            self.send_response(401);self.end_headers();return
        target=self.headers.get('X-Tls-Url','')
        path=urlsplit(target).path
        if path.endswith('speedtest-config.php'):
            body=b'<settings><client ip="127.0.0.1" lat="0" lon="0"/><server-config ignoreids="" threadcount="1"/><download threadsperurl="1" testlength="2"/><upload ratio="1" maxchunkcount="2" threads="2" testlength="2"/></settings>';typ='text/xml'
        elif path.endswith('speedtest-servers-static.php'):
            body=b'<settings><servers><server id="12345" url="http://unresolvable.invalid/speedtest/upload.php" lat="0" lon="0" name="Fixture" sponsor="Local"/></servers></settings>';typ='text/xml'
        elif path.endswith('latency.txt'):
            if not target.startswith('https://server-12345.prod.hosts.ooklaserver.net:8080/'):
                self.send_response(400);self.end_headers();return
            body=b'test=test';typ='text/plain'
        elif '/random' in path:
            time.sleep(.04);body=b'x'*32768;typ='image/jpeg'
        elif path.endswith('upload.php'):
            data=self.rfile.read(int(self.headers.get('Content-Length',0)));time.sleep(.06)
            body=('size='+str(len(data))).encode();typ='text/plain'
        else:
            self.send_response(404);self.end_headers();return
        self.send_response(200);self.send_header('Content-Type',typ);self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    def do_GET(self): self.handle_request()
    def do_POST(self): self.handle_request()
s=ThreadingHTTPServer(('127.0.0.1',0),Handler)
open(sys.argv[1],'w').write(str(s.server_port));s.serve_forever()
