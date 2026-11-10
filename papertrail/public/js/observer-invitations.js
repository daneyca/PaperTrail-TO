document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-observer-editor]');
    if (!form) return;
    const sections = form.querySelector('[data-observer-sections]');
    const clone = kind => document.getElementById(`observer-${kind}-template`).content.firstElementChild.cloneNode(true);
    const fieldPath = (node, path) => { node.dataset.letterPath=path; node.name=`letter[${path.split('.').join('][')}]`; };
    const renumber = () => {
        [...sections.children].forEach((section, si) => {
            fieldPath(section.querySelector('[data-letter-key="heading"]'), `sections.${si}.heading`);
            [...section.querySelector('[data-observer-schedules]').children].forEach((schedule, di) => {
                const base=`sections.${si}.schedules.${di}`;
                fieldPath(schedule.querySelector('[data-letter-key="when"]'), `${base}.when`);
                [...schedule.querySelector('[data-observer-projects]').children].forEach((project, pi) => {
                    project.querySelectorAll('[data-letter-key]').forEach(node=>fieldPath(node,`${base}.projects.${pi}.${node.dataset.letterKey}`));
                });
            });
        });
    };
    const resize = () => form.querySelectorAll('textarea[data-letter-path]').forEach(node => { node.style.setProperty('height','auto','important'); node.style.setProperty('height',`${node.scrollHeight+2}px`,'important'); });
    const mirror = () => form.querySelectorAll('[data-letter-path]').forEach(node => {
        const numeric=node.type==='number';
        node.nextElementSibling.textContent=numeric&&node.value!==''&&Number.isFinite(Number(node.value)) ? Number(node.value).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}) : node.value;
    });
    const read = () => {
        const data={};
        form.querySelectorAll('[data-letter-path]').forEach(node=>{
            const keys=node.dataset.letterPath.split('.'); let target=data;
            keys.forEach((key,i)=>{if(i===keys.length-1) target[key]=node.value; else {target[key]??=/^\d+$/.test(keys[i+1])?[]:{};target=target[key];}});
        });
        return {letter:data,record_id:form.elements.namedItem('record_id').value};
    };
    const fill = (container, data) => container.querySelectorAll('[data-letter-key]').forEach(node=>{if(Object.hasOwn(data,node.dataset.letterKey)) node.value=data[node.dataset.letterKey]??'';});
    const restore = saved => {
        if(!saved?.letter || !Array.isArray(saved.letter.sections) || !saved.letter.sections.length) return;
        const fragment=document.createDocumentFragment();
        saved.letter.sections.slice(0,8).forEach(sectionData=>{
            const section=clone('section'); fill(section,{heading:sectionData.heading});
            const schedules=section.querySelector('[data-observer-schedules]'); schedules.replaceChildren();
            (sectionData.schedules||[]).slice(0,30).forEach(scheduleData=>{
                const schedule=clone('schedule'); fill(schedule,{when:scheduleData.when});
                const projects=schedule.querySelector('[data-observer-projects]'); projects.replaceChildren();
                (scheduleData.projects||[]).slice(0,30).forEach(projectData=>{const project=clone('project');fill(project,projectData);projects.append(project);});
                schedules.append(schedule);
            });
            fragment.append(section);
        });
        sections.replaceChildren(fragment); renumber();
        form.querySelectorAll('[data-letter-path]').forEach(node=>{const key=node.dataset.letterPath;if(!key.includes('.')&&Object.hasOwn(saved.letter,key)) node.value=saved.letter[key]??'';});
        form.elements.namedItem('record_id').value=saved.record_id||'';
    };
    let timer;
    let modified=false;
    const backup=()=>{
        if(!modified) return;
        try {sessionStorage.setItem(form.dataset.draftKey,JSON.stringify(read()));}
        catch(error) {const notice=form.querySelector('[data-observer-storage-error]');notice.textContent='This browser could not back up your entries. Use Save Letter to keep your work.';notice.hidden=false;}
    };
    try {if(form.dataset.hasOldInput!=='true') {const saved=sessionStorage.getItem(form.dataset.draftKey);if(saved) {restore(JSON.parse(saved));modified=true;}}}catch(error){}
    const changed=()=>{modified=true;mirror();resize();clearTimeout(timer);timer=setTimeout(backup,250);};
    form.addEventListener('input',changed);
    form.addEventListener('change',changed);
    form.addEventListener('click',event=>{
        const add=event.target.closest('[data-observer-add]');
        const remove=event.target.closest('[data-observer-remove]');
        if(add) {
            const kind=add.dataset.observerAdd;
            const container=kind==='section'?sections:kind==='schedule'?add.closest('[data-observer-section]').querySelector('[data-observer-schedules]'):add.closest('[data-observer-schedule]').querySelector('[data-observer-projects]');
            if(container.children.length>=(kind==='section'?8:30)) return;
            const row=clone(kind); container.append(row);renumber();changed();row.querySelector('textarea,input')?.focus();
        }
        if(remove) {
            const row=remove.closest(`[data-observer-${remove.dataset.observerRemove}]`);
            if(row.parentElement.children.length<=1) return;
            const hasText=[...row.querySelectorAll('textarea,input')].some(node=>!['heading','budget_prefix'].includes(node.dataset.letterKey)&&node.value.trim());
            if(hasText&&!window.confirm('Remove this entry and its contents?')) return;
            row.remove();renumber();changed();
        }
    });
    form.querySelector('[data-observer-print]').addEventListener('click',()=>{mirror();window.print();});
    window.addEventListener('beforeprint',mirror);
    window.addEventListener('pagehide',backup);
    window.addEventListener('beforeunload',backup);
    form.addEventListener('submit',backup);
    renumber();mirror();resize();
});
