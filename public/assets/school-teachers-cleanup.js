(function(){
function clean(){document.querySelectorAll('#schoolTeacherRows tr td').forEach(function(cell){Array.prototype.slice.call(cell.childNodes).forEach(function(node){if(node.nodeType===3&&node.textContent.trim()==='Detail')node.remove()})})}
setInterval(clean,500);
})();
