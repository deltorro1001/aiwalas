(function(){var teacherIds={};
function refreshTeacherIds(){authRequest('api/guru').then(function(r){(r.data||[]).forEach(function(g){teacherIds[g.nama_lengkap]=g.id})})}
