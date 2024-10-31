window.onload = function(){
    require_touch = document.getElementById("pq_req_touchid");
    require_overall = document.getElementById("pq_req_passqi");
}

function pq_ToggleCheckbox(element){
    if(element.checked){
        document.getElementById("pq_fingerprint_disable").style.visibility = "visible"
    }else{
        document.getElementById("pq_fingerprint_disable").style.visibility = "hidden"
    }
}
        