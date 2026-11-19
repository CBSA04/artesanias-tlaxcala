"""HTTP acceptance tests. ONLY run against a disposable --demo database.
python3 tests/http_acceptance.py http://127.0.0.1:8080 --confirm-demo
Uses the Python standard library. Creates test users, a product and an order.
"""
import urllib.request,urllib.parse,urllib.error,http.cookiejar,re,sys,uuid,struct,zlib
assert '--confirm-demo' in sys.argv,'Use a disposable database and pass --confirm-demo'
BASE=sys.argv[1].rstrip('/')
COUNT=0
def ok(value,label):
 global COUNT
 assert value,label
 COUNT+=1;print('OK',label)
class Client:
 def __init__(self):self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 def get(self,path):
  try:r=self.opener.open(BASE+path)
  except urllib.error.HTTPError as err:r=err
  return r.code,r.read().decode(),r.url
 def post(self,path,data,files=None):
  if files:
   boundary='----artesanias'+uuid.uuid4().hex;parts=[]
   for k,v in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
   for k,(name,mime,content) in files.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
   body=b''.join(parts)+f'--{boundary}--\r\n'.encode();ctype='multipart/form-data; boundary='+boundary
  else:body=urllib.parse.urlencode(data).encode();ctype='application/x-www-form-urlencoded'
  req=urllib.request.Request(BASE+path,data=body,headers={'Content-Type':ctype})
  try:r=self.opener.open(req)
  except urllib.error.HTTPError as err:r=err
  return r.code,r.read().decode(),r.url
 def token(self,path='/ingresar'):
  code,body,_=self.get(path)
  token=re.search(r'name="_token" value="([a-f0-9]+)"',body)
  assert token,(code,body[:200]);return token[1]
 def login(self,email):return self.post('/ingresar',{'_token':self.token(),'email':email,'password':'Tlaxcala2026!'})
def png():
 def chunk(t,d):return struct.pack('!I',len(d))+t+d+struct.pack('!I',zlib.crc32(t+d)&0xffffffff)
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!2I5B',2,2,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\xc0\x70\x40'*0+b'\x00'+b'\xc0\x70\x40'*2+b'\x00'+b'\xc0\x70\x40'*2))+chunk(b'IEND',b'')
a=Client();code,home,_=a.get('/');ok(code==200 and 'Hecho en Tlaxcala' in home,'catálogo público')
ok(home.count('class="product-card"')>=9,'catálogo con imágenes y productos')
code,body,_=a.get('/producto/1');ok(code==200 and all(s in body for s in ['Materiales','historia','MXN','disponibles']),'ficha completa')
ok(a.post('/registro',{'name':'Sin token'})[0]==419,'protección CSRF')
ok('/ingresar' in a.get('/carrito')[2],'visitante no compra')
email='prueba-'+uuid.uuid4().hex[:10]+'@demo.test';password='Prueba2026!'
code,body,_=a.post('/registro',{'_token':a.token(),'name':'Cliente de prueba','email':email,'password':password,'password_confirmation':password,'role':'administrador'})
ok(code==200 and 'Cliente' in body,'registro e inicio de sesión')
ok(a.get('/admin')[0]==403 and a.get('/vendedor')[0]==403,'registro no permite elevar privilegios')
code,body,_=a.post('/carrito/agregar',{'_token':a.token('/producto/1'),'product_id':1,'quantity':1})
ok(code==200 and 'Resumen del pedido' in body,'agregar al carrito')
op=re.search(r'name="operation_key" value="([a-f0-9]+)"',body)[1]
code,body,link=a.post('/pedidos/confirmar',{'_token':a.token('/carrito'),'operation_key':op})
ok(code==200 and '/pedidos/' in link and 'No se ha realizado' in body,'compra e historial')
order_path=urllib.parse.urlparse(link).path
ok(a.post('/pedidos/confirmar',{'_token':a.token('/pedidos'),'operation_key':op})[2]==link,'reenvío no duplica pedido')
b=Client();b.login('cliente@demo.test');ok(b.get(order_path)[0]==404,'pedido privado por propietario')
s=Client();ok(s.login('vendedor@demo.test')[0]==200,'sesión vendedor')
ok('Pedidos de mi taller' in s.get('/vendedor')[1],'vendedor consulta pedidos de su taller')
ok(s.get('/admin')[0]==403 and s.get('/carrito')[0]==403,'permisos del vendedor')
fields={'_token':s.token('/vendedor/productos/nuevo'),'name':'Pieza de prueba '+uuid.uuid4().hex[:8],'category':'madera','municipality':'Tlaxcala','material':'Madera natural','utility':'Objeto decorativo','history':'Pieza creada para una prueba de aceptación.','source':'Prueba automatizada','price':'120.50','stock':'3','published':'1','rights':'1'}
ok(s.post('/vendedor/productos',fields,{'image':('bad.svg','image/svg+xml',b'<svg/>')})[0]==422,'rechazo de SVG subido')
code,body,_=s.post('/vendedor/productos',fields,{'image':('foto.png','image/png',png())})
ok(code==200 and fields['name'] in body,'alta de producto con imagen validada')
ids=re.findall(r'/vendedor/productos/(\d+)/editar',body);new_id=ids[0]
code,form,_=s.get('/vendedor/productos/'+new_id+'/editar');ok(code==200,'edición de ficha propia')
version=re.search(r'name="version" value="(\d+)"',form)[1]
fields.update(version=version,stock='4',price='130.50')
ok(s.post('/vendedor/productos/'+new_id,fields)[0]==200,'actualización de inventario')
ok(s.post('/vendedor/productos/'+new_id,fields)[0]==422,'edición desactualizada rechazada')
ad=Client();ok(ad.login('admin@demo.test')[0]==200,'sesión administrador')
ok(ad.get('/vendedor')[0]==403,'administrador respeta separación de roles')
newemail='vendedor-'+uuid.uuid4().hex[:8]+'@demo.test'
u={'_token':ad.token('/admin/usuarios/nuevo'),'name':'Segundo Taller','email':newemail,'password':'Tlaxcala2026!','role':'vendedor','active':'1'}
code,body,_=ad.post('/admin/usuarios',u);ok(code==200 and newemail in body,'administrador crea usuarios')
match=re.search(r'<td><strong>Segundo Taller</strong>.*?/admin/usuarios/(\d+)/editar',body,re.S);uid=match[1]
s2=Client();s2.login(newemail);ok(s2.get('/vendedor/productos/'+new_id+'/editar')[0]==404,'vendedor no edita productos ajenos')
f2=dict(fields);f2.update(_token=s2.token('/vendedor/productos/nuevo'),name='Pieza segundo taller',version='1')
code,body,_=s2.post('/vendedor/productos',f2,{'image':('foto.png','image/png',png())})
second_id=re.findall(r'/vendedor/productos/(\d+)/editar',body)[0]
ok(a.get('/producto/'+second_id)[0]==200,'pieza del nuevo vendedor es pública')
a.post('/carrito/agregar',{'_token':a.token('/producto/'+second_id),'product_id':second_id,'quantity':1})
u.update(name='Taller actualizado',_token=ad.token('/admin/usuarios/'+uid+'/editar'),password='')
ok(ad.post('/admin/usuarios/'+uid,u)[0]==200,'administrador modifica usuarios')
code,body,_=ad.post('/admin/usuarios/'+uid+'/eliminar',{'_token':ad.token('/admin/usuarios/'+uid+'/eliminar')})
ok(code==200,'administrador da de baja usuarios')
ok('/ingresar' in s2.get('/vendedor')[2],'baja revoca sesiones activas')
ok(Client().login(newemail)[0]==422,'cuenta inactiva no inicia sesión')
ok(a.get('/producto/'+second_id)[0]==404,'baja del vendedor retira su oferta pública')
ok('Sin disponibilidad suficiente' in a.get('/carrito')[1],'carrito impide comprar a taller inactivo')
# The initial administrator is the only active admin in a fresh demo database.
rows=re.findall(r'<tr>(.*?)</tr>',body,re.S);admin_id=None
for row in rows:
 if 'admin@demo.test' in row:admin_id=re.search(r'/admin/usuarios/(\d+)/editar',row)[1]
assert admin_id
code,body,_=ad.post('/admin/usuarios/'+admin_id+'/eliminar',{'_token':ad.token('/admin')})
ok('al menos un administrador activo' in body,'protección del último administrador')
code,body,_=ad.get('/?category=textiles&sort=price_asc');ok(code==200 and body.count('class="product-card"')==3,'filtro por categoría')
code,body,_=ad.get('/?active_category=textiles&q=coj');ok(code==200 and body.count('class="product-card"')==1,'búsqueda conserva categoría')
for path in ['/assets/app.css','/assets/app.js','/assets/favicon.svg','/assets/products/sarape.svg','/acerca','/no-existe']:
 ok(ad.get(path)[0]==(404 if path=='/no-existe' else 200),'ruta '+path)
# Authentication attempt limit for one newly generated, nonexistent account.
limited=Client();unknown='missing-'+uuid.uuid4().hex[:8]+'@demo.test'
for _ in range(5):limited.post('/ingresar',{'_token':limited.token(),'email':unknown,'password':'incorrecta'})
ok(limited.post('/ingresar',{'_token':limited.token(),'email':unknown,'password':'incorrecta'})[0]==429,'límite de intentos de acceso')
ok(a.post('/salir',{'_token':a.token('/cuenta')})[0]==200,'cierre de sesión')
ok('/ingresar' in a.get('/pedidos')[2],'sesión cerrada pierde acceso a pedidos')
print(f'{COUNT} comprobaciones HTTP correctas')
