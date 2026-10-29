import sharp from 'sharp'; import fs from 'fs'; import path from 'path';
const [,, dir, out] = process.argv;
const files = fs.readdirSync(dir).filter(f=>/\.jpe?g$/i.test(f)).sort();
const W=300,H=300,cols=5; const rows=Math.ceil(files.length/cols);
const comps=[];
for (let i=0;i<files.length;i++){
  const b=await sharp(path.join(dir,files[i])).rotate().resize(W,H,{fit:'cover'}).toBuffer();
  comps.push({input:b,left:(i%cols)*W,top:Math.floor(i/cols)*H});
  const svg=Buffer.from(`<svg width="${W}" height="30"><rect width="60" height="30" fill="black"/><text x="8" y="21" font-size="20" fill="white">${i+1}</text></svg>`);
  comps.push({input:svg,left:(i%cols)*W,top:Math.floor(i/cols)*H});
}
await sharp({create:{width:cols*W,height:rows*H,channels:3,background:'#fff'}}).composite(comps).jpeg({quality:70}).toFile(out);
console.log(files.map((f,i)=>`${i+1}:${f}`).join(' '));
