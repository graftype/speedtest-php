from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import sys
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *args): pass
    def do_GET(self):
        if self.path.startswith('/legacy/latency.txt'):
            self.send_response(307)
            self.send_header('Location', '/canonical/latency.txt')
            self.end_headers()
        elif self.path.startswith('/canonical/latency.txt'):
            self.send_response(200); self.end_headers(); self.wfile.write(b'test=test')
        else:
            self.send_response(404); self.end_headers()
    def do_POST(self):
        data = self.rfile.read(int(self.headers.get('Content-Length', 0)))
        if self.path.startswith('/legacy/'):
            # Reproduce an empty HTTP reply before a large POST can follow its redirect.
            self.close_connection = True
            return
        self.send_response(200); self.send_header('Content-Type', 'text/plain'); self.end_headers()
        self.wfile.write(('size=' + str(len(data))).encode())
server = ThreadingHTTPServer(('127.0.0.1', 0), Handler)
open(sys.argv[1], 'w').write(str(server.server_port))
server.serve_forever()
