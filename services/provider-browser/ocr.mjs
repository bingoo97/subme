import {spawn} from 'node:child_process';

// Local process only: PNG through stdin, bounded TSV through stdout. No files, shell, logs or external services.
export function parseCaptchaTSV(value,{digits=false,length=0}={}){
 if(typeof value!=='string'||Buffer.byteLength(value)>16384)return null;
 const lines=value.trim().split(/\r?\n/),words=[];
 if(lines[0]!=='level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext')return null;
 for(const row of lines.slice(1)){
  const fields=row.split('\t');if(fields[0]!=='5')continue;
  if(fields.length!==12)return null;
  const text=fields[11].trim(),confidence=Number(fields[10]);
  if(!text)continue;
  if(!Number.isFinite(confidence)||confidence<92||confidence>100)return null;
  words.push(text);
 }
 // Do not stitch fragments or guess missing/ambiguous characters.
 if(words.length!==1||!(digits?/^[0-9]{4,12}$/:/^[A-Za-z0-9]{4,12}$/).test(words[0])||(length&&words[0].length!==length))return null;
 return words[0];
}

export async function recognizeCaptcha(png,options={}, {spawnProcess=spawn}={}){
 if(!Buffer.isBuffer(png)||png.length<24||png.length>262144||!png.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10]))||png.toString('ascii',12,16)!=='IHDR')return null;
 const width=png.readUInt32BE(16),height=png.readUInt32BE(20);
 if(width<8||height<8||width>600||height>300)return null;
 return new Promise(resolve=>{
  let child,done=false,output='',timer;
  const finish=result=>{if(done)return;done=true;clearTimeout(timer);resolve(result);};
  try{
   child=spawnProcess('/usr/bin/tesseract',['stdin','stdout','-l','eng','--psm','7','--dpi','150','-c','tessedit_char_whitelist='+(options.digits?'0123456789':'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'),'-c','load_system_dawg=0','-c','load_freq_dawg=0','tsv'],{
    shell:false,stdio:['pipe','pipe','ignore'],env:{PATH:'/usr/bin:/bin',LANG:'C.UTF-8',OMP_THREAD_LIMIT:'1'}
   });
   timer=setTimeout(()=>{child.kill('SIGKILL');finish(null);},2500);
   child.once('error',()=>finish(null));
   child.stdout.on('data',chunk=>{if(done)return;output+=chunk.toString('utf8');if(Buffer.byteLength(output)>16384){child.kill('SIGKILL');finish(null);}});
   child.stdin.on('error',()=>{});
   child.once('close',code=>finish(code===0?parseCaptchaTSV(output,options):null));
   child.stdin.end(png);
  }catch{child?.kill('SIGKILL');finish(null);}
 });
}
