import {createHash} from 'node:crypto';
import {recognizeCaptcha} from './ocr.mjs';
import {submit} from './engine.mjs';

export async function autoCaptchaLogin(page,config,outcome,{recognize=recognizeCaptcha}={}){
 if(outcome.code!=='captcha_required'||config.captcha_ocr!==true||config.two_factor)return outcome;
 const fallback=()=>({...outcome,ocr_fallback:true});
 let answer='';
 try{
  const input=page.locator(config.captcha_input),image=page.locator(config.captcha_image);
  const constraints=await input.evaluate(el=>({max:el.maxLength,pattern:el.getAttribute('pattern')||'',mode:el.inputMode}));
  const exact=/^(?:\^)?(?:\\d|\[0-9\])\{(\d{1,2})\}(?:\$)?$/.exec(constraints.pattern);
  const options={digits:config.captcha_ocr_digits===true||!!exact,length:exact?Number(exact[1]):0};
  answer=await recognize(Buffer.from(outcome.image.split(',')[1],'base64'),options);
  if(!answer||(constraints.max>0&&answer.length>constraints.max))return fallback();
  // A CAPTCHA can rotate while OCR runs. Never submit an answer for an older image.
  const current=await image.screenshot({type:'png',timeout:2000});
  const hash=value=>createHash('sha256').update(value).digest('hex');
  if(hash(current)!==hash(Buffer.from(outcome.image.split(',')[1],'base64')))return fallback();
 }catch{return fallback();}
 // Once submission begins, an error must close the session, never offer a second credential POST.
 const code=await submit(page,config,answer);answer='';
 return {code,ocr_used:true};
}
