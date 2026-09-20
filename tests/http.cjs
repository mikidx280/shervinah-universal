// Node's bundled fetch can assert on the PHP development server's multipart redirects.
// Use core HTTP for transport; Request only serializes the standard form bodies.
const http=require('node:http');
module.exports=async function fixtureFetch(url,options={}){
  const request=new Request(url,options);const body=options.body?Buffer.from(await request.arrayBuffer()):null;
  const headers=Object.fromEntries(request.headers);headers.connection='close';if(body)headers['content-length']=body.length;
  return new Promise((resolve,reject)=>{const req=http.request(url,{method:request.method,headers},res=>{const chunks=[];res.on('data',x=>chunks.push(x));res.on('end',()=>resolve({status:res.statusCode,headers:new Headers(res.headers),text:async()=>Buffer.concat(chunks).toString(),json:async()=>JSON.parse(Buffer.concat(chunks).toString())}));});req.on('error',reject);req.end(body);});
};
