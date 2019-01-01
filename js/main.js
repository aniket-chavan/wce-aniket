var LSK='pma_',LSKX=LSK+'max',LSKM=LSK+'min',qcur=0,LSMAX=32;

function $(i){return document.getElementById(i)}
function frefresh(){
 var F=document.DF;
 F.method='get';
 F.refresh.value="1";
 F.GoSQL.click();
}
function go(p,sql){
 var F=document.DF;
 F.p.value=p;
 if(sql)F.q.value=sql;
 F.GoSQL.click();
}
function ays(){
 return confirm('Are you sure to continue?');
}
function chksql(){
 var F=document.DF,v=F.qraw.value;
 if(/^\s*(?:delete|drop|truncate|alter)/.test(v)) if(!ays())return false;
 if(lschk(1)){
  var lsm=lsmax()+1,ls=localStorage;
  ls[LSK+lsm]=v;
  ls[LSKX]=lsm;
  //keep just last LSMAX queries in log
  if(!ls[LSKM])ls[LSKM]=1;
  var lsmin=parseInt(ls[LSKM]);
  if((lsm-lsmin+1)>LSMAX){
   lsclean(lsmin,lsm-LSMAX);
  }
 }
 return true;
}
function tc(tr){
 if (tr.className=='s'){
  tr.className=tr.classNameX;
 }else{
  tr.classNameX=tr.className;
  tr.className='s';
 }
}
function lschk(skip){
 if (!localStorage || !skip && !localStorage[LSKX]) return false;
 return true;
}
function lsmax(){
 var ls=localStorage;
 if(!lschk() || !ls[LSKX])return 0;
 return parseInt(ls[LSKX]);
}
function lsclean(from,to){
 ls=localStorage;
 for(var i=from;i<=to;i++){
  delete ls[LSK+i];ls[LSKM]=i+1;
 }
}
function q_prev(){
 var ls=localStorage;
 if(!lschk())return;
 qcur--;
 var x=parseInt(ls[LSKM]);
 if(qcur<x)qcur=x;
 $('qraw').value=ls[LSK+qcur];
}
function q_next(){
 var ls=localStorage;
 if(!lschk())return;
 qcur++;
 var x=parseInt(ls[LSKX]);
 if(qcur>x)qcur=x;
 $('qraw').value=ls[LSK+qcur];
}
function after_load(){
 var F=document.DF;
 var p=F['v[pwd]'];
 if (p) p.focus();
 qcur=lsmax();

 F.addEventListener('submit',function(e){
  if(!F.qraw)return;
  if(!chksql()){e.preventDefault();return}
  $('q').value=btoa(encodeURIComponent($('qraw').value).replace(/%([0-9A-F]{2})/g,function(m,p){return String.fromCharCode('0x'+p)}));
 });
 var res=$('res');
 if(res)res.addEventListener('dblclick',function(e){
  if(!$('is_sm').checked)return;
  var el=e.target;
  if(el.tagName!='TD')el=el.parentNode;
  if(el.tagName!='TD')return;
  if(el.className.match(/\b\lg\b/))el.className=el.className.replace(/\blg\b/,' ');
  else el.className+=' lg';
 });
}
function logoff(){
 if(lschk()){
  var ls=localStorage;
  var from=parseInt(ls[LSKM]),to=parseInt(ls[LSKX]);
  for(var i=from;i<=to;i++){
   delete ls[LSK+i];
  }
  delete ls[LSKM];delete ls[LSKX];
 }
}
function cfg_toggle(){
 var e=$('cfg-adv');
 e.style.display=e.style.display=='none'?'':'none';
}
function qtpl(s){
 $('qraw').value=s.replace(/%T/g,'`<?php echo $_REQUEST['t']?b64d($_REQUEST['t']):'tablename'?>`');
}
function smview(){
 if($('is_sm').checked){$('res').className+=' sm'}else{$('res').className = $('res').className.replace(/\bsm\b/,' ')}
}
<?php if($is_sht){?>
function chkall(cab){
 var e=document.DF.elements;
 if (e!=null){
  var cl=e.length;
  for (i=0;i<cl;i++){var m=e[i];if(m.checked!=null && m.type=="checkbox"){m.checked=cab.checked}}
 }
}
function sht(f){
 document.DF.dosht.value=f;
}
<?php }?>


