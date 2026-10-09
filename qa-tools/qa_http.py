import urllib.request,urllib.error,http.cookiejar,json as jsonlib,uuid
class Response:
 def __init__(self,response):
  self.status_code=response.status if hasattr(response,'status') else response.code;self.headers=response.headers;self.content=response.read();self.text=self.content.decode('utf-8',errors='replace')
 def json(self):return jsonlib.loads(self.text)
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,req,fp,code,msg,headers,newurl):return None
class Session:
 def __init__(self):self.headers={};self.jar=http.cookiejar.CookieJar()
 def request(self,method,url,json=None,files=None,timeout=15,allow_redirects=True):
  headers=dict(self.headers);body=None
  if json is not None:body=jsonlib.dumps(json).encode();headers['Content-Type']='application/json'
  if files:
   boundary='QA'+uuid.uuid4().hex;parts=[]
   for name,(filename,data,mime) in files.items():parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode()+data+b'\r\n')
   body=b''.join(parts)+f'--{boundary}--\r\n'.encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
  handlers=[urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar)]
  if not allow_redirects:handlers.append(NoRedirect())
  opener=urllib.request.build_opener(*handlers);req=urllib.request.Request(url,data=body,headers=headers,method=method)
  try:return Response(opener.open(req,timeout=timeout))
  except urllib.error.HTTPError as error:return Response(error)
def get(url,**kwargs):return Session().request('GET',url,**kwargs)
