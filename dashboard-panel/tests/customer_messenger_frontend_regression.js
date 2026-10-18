'use strict';
// Run the actual customer Messenger controller with deterministic async transport.
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../public_html/assets/js/messanger.js'),'utf8');
let checks=0;const ck=(v,label)=>{assert.ok(v,label);checks++;};
function harness(){
 const nodes=new Map(),requests=[],events=[],timers=new Map();let timerId=0;
 function node(key){if(!nodes.has(key))nodes.set(key,{length:1,value:'',props:{},styles:{},focus:0,val(v){if(v===undefined)return this.value;this.value=v;return this;},prop(k,v){this.props[k]=v;return this;},trigger(e){if(e==='focus')this.focus++;return this;},css(k,v){this.styles[k]=v;return this;},on(...args){events.push(args);return this;},data(){return 1;}});return nodes.get(key);}
 function deferred(){const done=[],fail=[],always=[];let settled=false;return {done(f){done.push(f);return this;},fail(f){fail.push(f);return this;},always(f){always.push(f);return this;},resolve(p){if(!settled){settled=true;done.forEach(f=>f(p));always.forEach(f=>f());}return this;},reject(status){if(!settled){settled=true;fail.forEach(f=>f({},status));always.forEach(f=>f());}return this;},abort(){this.reject('abort');},promise(){return this;},late(p){done.forEach(f=>f(p));always.forEach(f=>f());}};}
 const $=arg=>typeof arg==='function'?undefined:node(arg);$.trim=s=>String(s||'').trim();$.extend=Object.assign;$.Deferred=deferred;$.ajax=options=>{const r=deferred();r.options=options;requests.push(r);return r;};
 const document={hidden:false,documentElement:{clientHeight:844}};
 const window={jQuery:$,MESSENGER_BOOTSTRAP:{},innerHeight:844,FormData:class{append(){}},setTimeout(f){let id=++timerId;timers.set(id,f);return id;},clearTimeout(id){timers.delete(id);}};
 const context=vm.createContext({window,document,console,$});vm.runInContext(source,context);const m=window.MessengerUI;
 m.config=()=>({userId:1,csrfToken:'fixture',endpoint:'/check_chat.php'});m.input=()=>node('input');m.widget=()=>node('widget');
 for(const key of ['updateComposerAvailability','closeMessageActions','clearReplyTarget','resetComposerLinkPreview','queueLinkPreviewRefresh','appendLocalSentMessage','scheduleSyncAfterSend','saveActiveConversationState','syncConversationUrlState','showConversationView','setConversationLoadingState','applyCooldown'])m[key]=()=>{};
 m.showNotice=message=>{m.notice=message;};m.hasResellerInboxLayout=()=>true;m.isOpen=()=>true;m.showConversationList=()=>{m.resellerViewMode='list';};m.renderPayload=p=>{m.rendered=p;m.activeCanSend=true;};m.extractFirstUrl=()=>'';
 return {m,$,context,window,document,requests,node,events,timers};
}
{
 const h=harness(),{m,requests}=h;m.activeConversationId=1;
 m.fetch();ck(requests.length===1 && requests[0].options.timeout===20000,'Polling has a bounded request timeout');
 m.selectConversation(2,'group_chat');ck(requests.length===2,'Changing conversation aborts old polling and starts the requested conversation immediately');
 requests[0].late({ok:true,html:'old'});ck(!m.rendered && m.fetchInFlight,'Stale reply cannot repaint the new conversation or release its request guard');
 requests[1].resolve({ok:true,html:'new'});ck(m.rendered.html==='new' && !m.fetchInFlight,'Latest conversation renders and unlocks normally');
 m.selectConversation(3,'group_chat');requests[2].reject('timeout');ck(m.resellerViewMode==='list' && !m.activeCanSend && m.notice,'Network failure returns to inbox and does not leave an old conversation writable');
 m.selectConversation(4,'group_chat');requests[3].resolve({ok:false,message:'Access denied'});ck(m.notice==='Access denied' && !m.activeCanSend,'Rejected conversation remains unwritable with a useful message');
 m.sendInFlight=true;let n=requests.length;m.selectConversation(5,'group_chat');ck(requests.length===n && m.activeConversationId===4,'Cannot switch recipients while a message is being sent');
}
{
 const h=harness(),{m,requests,node}=h;m.activeConversationId=1;node('input').value='first';m.fetch();m.sendMessage();m.sendMessage();
 ck(requests.length===2 && requests[1].options.data.tresc==='first','Double send submits one message and aborts preceding polling');
 m.isOpen=()=>false;requests[1].resolve({ok:true,html:'sent'});ck(!m.sendInFlight && node('input').focus===0 && !node('input').props.disabled,'Late success unlocks without reopening a closed mobile keyboard');
 m.isOpen=()=>true;node('input').value='retry';m.sendMessage();requests[2].reject('timeout');ck(node('input').value==='retry' && !m.sendInFlight,'Failed send keeps the text available for an explicit retry');
}
{
 const h=harness(),{m,context,events}=h;let sends=0;m.sendMessage=()=>sends++;context.self=m;
 const start=source.indexOf("\t\t\t$(document).on('keydown.messengerUi', '#tresc'");const end=source.indexOf("\n\t\t\t$(document).on('input.messengerUi'",start);vm.runInContext(source.slice(start,end),context);
 const key=events[0][2];const e={which:13,shiftKey:false,preventDefault(){}};
 key({...e,isComposing:true});key({...e,originalEvent:{isComposing:true}});key({...e,keyCode:229});key({...e,shiftKey:true});ck(sends===0,'IME and Shift+Enter cannot accidentally submit text');key(e);ck(sends===1,'Ordinary Enter sends once');
}
{
 const h=harness(),{m}=h;let closed=0,inbox=0;m.getCurrentUnreadCount=()=>3;m.close=()=>closed++;m.openInboxFromToggle=()=>{inbox++;m.resellerViewMode='list';};m.resellerViewMode='conversation';m.handleToggleButtonAction();m.handleToggleButtonAction();ck(inbox===1&&closed===1,'Unread notifications cannot trap the user in an unclosable mobile inbox');
}
{
 const h=harness(),{m,node,requests}=h;m.groupNameInput=()=>node('name');node('name').value='Fixture group';m.groupInviteValues=()=>[];m.groupRetentionCreate=()=>node('retention');node('retention').value='24h';m.groupCreationKind='group';m.showGroupAlert=()=>{};
 m.submitGroupCreate();m.submitGroupCreate();ck(requests.length===1&&requests[0].options.timeout===120000,'Double click cannot create two groups and file uploads have a bounded longer timeout');requests[0].resolve({ok:false});ck(!m.groupSubmitInFlight,'Failed group creation permits retry');m.submitGroupCreate();ck(requests.length===2,'Deliberate group creation retry works');
}
{
 const h=harness(),{m,window,node,document,requests,timers}=h;window.visualViewport={height:260,offsetTop:32};m.updateViewportMetrics();ck(node('widget').styles['--messenger-mobile-vh']==='260px'&&node('widget').styles['--messenger-mobile-offset-top']==='32px','Composer layout follows the actual viewport when a mobile keyboard opens');
 document.hidden=true;m.schedulePoll();const callback=[...timers.values()][0];callback();ck(requests.length===0,'A hidden tab does not poll the server');
}
{
 const h=harness(),{m,requests}=h;m.activeConversationId=1;m.toggleReaction(10,'like');m.selectConversation(2,'group_chat');requests[0].resolve({ok:true,html:'old reaction'});ck(!m.rendered,'Late reaction cannot repaint a different conversation');requests[1].resolve({ok:true,html:'current'});ck(m.rendered.html==='current','Current conversation remains selected after a late reaction');
}
console.log(`PASS: ${checks} customer Messenger race/network/keyboard assertions`);
