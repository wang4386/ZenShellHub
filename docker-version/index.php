<?php
// ==========================================
// 核心后端逻辑 (PHP)
// ==========================================

// 配置: 优先读取环境变量，以适配 Docker/Vercel 等环境
$dataFile = getenv('ZEN_DATA_PATH') ?: __DIR__ . '/data.json';
$htaccessFile = __DIR__ . '/.htaccess';
$skipHtaccess = getenv('ZEN_SKIP_HTACCESS') === 'true';

// 1. 安全防护：自动创建 .htaccess 禁止直接访问 data.json
// 注意：如果数据文件不在当前目录，或者明确跳过，则不创建
if (!$skipHtaccess && dirname($dataFile) === __DIR__ && !file_exists($htaccessFile)) {
    @file_put_contents($htaccessFile, "<Files \"" . basename($dataFile) . "\">\n  Order Deny,Allow\n  Deny from all\n</Files>");
}

// 2. 数据层封装
function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return ["meta" => ["password_hash" => null], "scripts" => []];
    }
    $content = file_get_contents($dataFile);
    $json = json_decode($content, true);
    if (isset($json[0]) || empty($json)) {
        return ["meta" => ["password_hash" => null], "scripts" => is_array($json) ? $json : []];
    }
    return $json;
}

function saveData($data) {
    global $dataFile;
    // 确保目录存在
    $dir = dirname($dataFile);
    if (!is_dir($dir)) {
        // 尝试创建目录，如果失败返回错误
        if (!@mkdir($dir, 0777, true)) {
            $err = error_get_last();
            return "无法创建数据目录 ($dir): " . ($err['message'] ?? '未知权限错误');
        }
        // 赋予目录宽松权限以防止 Docker 权限问题
        @chmod($dir, 0777);
    }
    
    // 尝试写入数据
    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $result = @file_put_contents($dataFile, $jsonContent);
    
    if ($result === false) {
        $err = error_get_last();
        return "写入文件失败 (" . basename($dataFile) . "): " . ($err['message'] ?? 'Permission denied');
    }
    return true; // 成功返回 true
}

// 3. API 路由处理
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $currentData = loadData();

    if ($action === 'init_check') {
        $needsSetup = empty($currentData['meta']['password_hash']);
        echo json_encode(['status' => 'success', 'needsSetup' => $needsSetup]);
        exit;
    }

    if ($action === 'setup_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($currentData['meta']['password_hash'])) { echo json_encode(['status' => 'error', 'message' => '密码已存在']); exit; }
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['password'])) { echo json_encode(['status' => 'error', 'message' => '密码不能为空']); exit; }
        $currentData['meta']['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $saveResult = saveData($currentData);
        if ($saveResult === true) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $saveResult]);
        }
        exit;
    }

    if ($action === 'verify_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (password_verify($input['password'], $currentData['meta']['password_hash'] ?? '')) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(401); echo json_encode(['status' => 'error', 'message' => '密码错误']);
        }
        exit;
    }

    if ($action === 'get_data') { echo json_encode($currentData['scripts']); exit; }

    if ($action === 'save_data' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']); exit; }
        $currentData['scripts'] = $input;
        
        $saveResult = saveData($currentData);
        if ($saveResult === true) {
            echo json_encode(['status' => 'success']);
        } else { 
            http_response_code(500); 
            echo json_encode(['status' => 'error', 'message' => $saveResult]); 
        }
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zen Shell Hub</title>
    
    <!-- React & Tailwind -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/framer-motion@10.16.4/dist/framer-motion.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Fonts & Config -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], mono: ['SF Mono', 'Menlo', 'monospace'] },
                    colors: { ios: { bg: '#F2F2F7', card: 'rgba(255, 255, 255, 0.75)', dark: '#1C1C1E' } },
                    boxShadow: { 'float': '0 24px 48px -12px rgba(0, 0, 0, 0.08)' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F2F2F7; -webkit-font-smoothing: antialiased; }
        .code-scroll::-webkit-scrollbar { height: 6px; }
        .code-scroll::-webkit-scrollbar-thumb { background: #4A4A4C; border-radius: 4px; }
        .backdrop-blur-heavy { backdrop-filter: blur(40px) saturate(180%); -webkit-backdrop-filter: blur(40px) saturate(180%); }
        .icon-box { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        /* Removed old clamp hover utility as it is replaced by JS/Structure logic */
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect, useRef, useMemo } = React;
        const { motion, AnimatePresence } = window.Motion;

        // --- Basic UI Components ---
        const Icon = ({ name, size = 18, className = "" }) => {
            const r = useRef(null);
            useEffect(() => {
                if (r.current) {
                    r.current.innerHTML = `<i data-lucide="${name}"></i>`;
                    lucide.createIcons({ root: r.current, attrs: { width: size, height: size, class: className } });
                }
            }, [name, size, className]);
            return <span ref={r} className={`icon-box w-[${size}px] h-[${size}px]`} style={{width:size, height:size}}></span>;
        };

        // Optimized Toast: Uses x: "-50%" in motion to prevent Tailwind conflict, strictly centered
        const Toast = ({ message, type, onClose }) => {
            useEffect(() => { const t = setTimeout(onClose, 3000); return () => clearTimeout(t); }, [onClose]);
            return (
                <motion.div 
                    initial={{opacity:0, y:50, x: "-50%"}} 
                    animate={{opacity:1, y:0, x: "-50%"}} 
                    exit={{opacity:0, y:20, x: "-50%"}} 
                    className={`fixed bottom-8 left-1/2 z-[200] px-6 py-3 rounded-full shadow-2xl text-white font-bold flex items-center gap-2 whitespace-nowrap ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`}
                >
                    {message}
                </motion.div>
            );
        };

        const CodeBlock = ({ command, wrapCode }) => {
            const [copied, setCopied] = useState(false);
            const copy = async () => {
                try {
                    await navigator.clipboard.writeText(command);
                    setCopied(true); setTimeout(() => setCopied(false), 2000);
                } catch {
                    const t = document.createElement("textarea"); t.value = command; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t);
                    setCopied(true); setTimeout(() => setCopied(false), 2000);
                }
            };
            return (
                <div className="group/code relative rounded-xl overflow-hidden shadow-inner bg-[#1e1e1e] border border-white/10 mt-4 transition-all hover:shadow-lg">
                    <div className="flex items-center px-4 py-2 bg-[#2d2d2d] border-b border-white/5">
                        <div className="flex gap-1.5"><div className="w-3 h-3 rounded-full bg-[#FF5F56]"></div><div className="w-3 h-3 rounded-full bg-[#FFBD2E]"></div><div className="w-3 h-3 rounded-full bg-[#27C93F]"></div></div>
                        <div className="ml-auto text-[10px] text-gray-500 font-mono">bash</div>
                    </div>
                    <div className={`p-4 font-mono text-sm text-gray-200 ${wrapCode ? 'break-all whitespace-pre-wrap' : 'overflow-x-auto whitespace-nowrap code-scroll'}`}>
                        <span className="text-[#27C93F] mr-2">$</span>{command}
                    </div>
                    <button onClick={copy} className="absolute bottom-3 right-3 p-2 bg-white/10 backdrop-blur-md border border-white/10 rounded-lg text-white opacity-0 group-hover/code:opacity-100 transition-opacity active:scale-95 flex items-center gap-1.5 hover:bg-white/20">
                        <Icon name={copied ? "check" : "copy"} size={14} /><span className="text-xs font-medium">{copied ? '已复制' : '复制'}</span>
                    </button>
                </div>
            );
        };

        const ScriptCard = ({ script, isAdmin, isSelected, toggleSelect, onEdit, onDelete, onImageHover }) => {
            return (
                <motion.div layout initial={{opacity:0, scale:0.95}} animate={{opacity:1, scale:1}} className={`relative flex flex-col h-full bg-ios-card backdrop-blur-[50px] rounded-[32px] border border-white/60 p-1 shadow-float group/card transition-all duration-300 ${isSelected ? 'ring-2 ring-blue-500/50 scale-[1.01]' : 'hover:bg-white/80'}`}>
                    <div className="flex-1 flex flex-col p-5 rounded-[28px] border border-gray-200/40 h-full relative overflow-hidden">
                        {isAdmin && (
                            <div className="absolute top-5 right-14 z-20 flex gap-2 opacity-0 group-hover/card:opacity-100 transition-opacity duration-200">
                                <button onClick={(e) => { e.stopPropagation(); onEdit(script); }} className="p-1.5 bg-white/90 backdrop-blur rounded-lg shadow-sm border border-gray-200 hover:text-blue-600 hover:scale-110 transition-all"><Icon name="edit-3" size={14}/></button>
                                <button onClick={(e) => { e.stopPropagation(); if(confirm('删除?')) onDelete(script.id); }} className="p-1.5 bg-white/90 backdrop-blur rounded-lg shadow-sm border border-gray-200 hover:text-red-600 hover:scale-110 transition-all"><Icon name="trash-2" size={14}/></button>
                            </div>
                        )}
                        <div className="flex justify-between items-start mb-3 relative z-10">
                            <div className="flex-1 pr-8">
                                <div className="flex items-center gap-2 mb-1">
                                    <h3 className="text-lg font-bold text-gray-900">{script.title}</h3>
                                    {script.source?.name && <a href={script.source.url||'#'} target="_blank" className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-[10px] font-bold text-gray-500 uppercase hover:bg-gray-200 transition-colors">{script.source.name}<Icon name="external-link" size={10}/></a>}
                                </div>
                                <div className="flex flex-wrap gap-2 mt-2">{script.tags.map((tag,i)=><span key={i} className="px-2.5 py-1 rounded-lg bg-gray-100/80 text-[10px] font-bold text-gray-600 border border-gray-200/50">#{tag}</span>)}</div>
                            </div>
                            <button onClick={()=>toggleSelect(script.id)} className={`w-6 h-6 rounded-full border flex items-center justify-center transition-all flex-shrink-0 ${isSelected ? 'bg-blue-500 border-blue-500 text-white' : 'border-gray-300 text-transparent hover:border-gray-400'}`}>
                                <Icon name="check" size={14} className={isSelected?"text-white":""} />
                            </button>
                        </div>
                        
                        <div className="group/desc relative z-10 mb-4">
                            <p className="text-sm text-gray-500 line-clamp-2 transition-opacity duration-200 group-hover/desc:opacity-0 min-h-[2.5rem]">
                                {script.description}
                            </p>
                            
                            <div className="absolute -top-2 -left-3 -right-3 opacity-0 scale-95 pointer-events-none group-hover/desc:opacity-100 group-hover/desc:scale-100 group-hover/desc:pointer-events-auto transition-all duration-200 z-50 origin-top">
                                <div className="bg-white/90 backdrop-blur-xl rounded-xl shadow-2xl border border-gray-100 ring-1 ring-black/5 p-3 text-sm text-gray-700 leading-relaxed break-words">
                                    {script.description}
                                </div>
                            </div>
                        </div>

                        {script.image && (
                            <div className="relative mb-4 rounded-xl h-32 w-full z-0 cursor-zoom-in" onMouseEnter={(e) => onImageHover(script.image)} onMouseLeave={() => onImageHover(null)}>
                                <div className="w-full h-full overflow-hidden rounded-xl bg-gray-50 border border-gray-200/50"><img src={script.image} className="w-full h-full object-cover" onError={(e)=>e.target.style.display='none'}/></div>
                            </div>
                        )}
                        <div className="mt-auto relative z-10"><CodeBlock command={script.command} wrapCode={script.wrapCode}/></div>
                    </div>
                </motion.div>
            );
        };

        const App = () => {
            const [isAdmin, setIsAdmin] = useState(() => localStorage.getItem('zen_auth') === 'true');
            const [needsSetup, setNeedsSetup] = useState(false);
            const [scripts, setScripts] = useState([]);
            const [searchQuery, setSearchQuery] = useState('');
            const [selectedIds, setSelectedIds] = useState([]);
            const [toast, setToast] = useState(null);
            const [isSharedView, setIsSharedView] = useState(false);
            
            const [showLogin, setShowLogin] = useState(false);
            const [showEditor, setShowEditor] = useState(false);
            const [showShare, setShowShare] = useState(false);
            const [editingScript, setEditingScript] = useState(null);
            const [hoveredImage, setHoveredImage] = useState(null);
            
            const passRef = useRef();
            const setupPassRef = useRef();

            useEffect(() => {
                const init = async () => {
                    const params = new URLSearchParams(window.location.search);
                    const isShare = !!params.get('ids');
                    setIsSharedView(isShare);
                    
                    try {
                        const checkRes = await fetch('?action=init_check');
                        const checkData = await checkRes.json();
                        if (checkData.needsSetup) {
                            setNeedsSetup(true);
                        } else {
                            const localAuth = localStorage.getItem('zen_auth') === 'true';
                            if (localAuth || isShare) {
                                loadScripts();
                            }
                        }
                    } catch (e) { console.error(e); }
                };
                init();
            }, []);

            const loadScripts = async () => {
                try {
                    const res = await fetch('?action=get_data&t=' + Date.now());
                    const data = await res.json();
                    setScripts(Array.isArray(data) ? data : []);
                } catch { setToast({message:'数据加载失败', type:'error'}); }
            };

            const saveData = async (newScripts) => {
                setScripts(newScripts);
                try {
                    const res = await fetch('?action=save_data', { method: 'POST', body: JSON.stringify(newScripts) });
                    const r = await res.json();
                    if(r.status === 'success') setToast({message:'已保存', type:'success'});
                    else throw new Error(r.message);
                } catch(e) { setToast({message: e.message, type:'error'}); }
            };

            const handleSetup = async (e) => {
                e.preventDefault();
                const pwd = setupPassRef.current.value;
                if(!pwd) return alert('密码不能为空');
                try {
                    const res = await fetch('?action=setup_password', { method: 'POST', body: JSON.stringify({password: pwd}) });
                    const r = await res.json();
                    if(r.status === 'success') { setNeedsSetup(false); setIsAdmin(true); localStorage.setItem('zen_auth', 'true'); setToast({message: '初始化成功', type: 'success'}); loadScripts(); } 
                    else alert(r.message);
                } catch { alert('设置失败'); }
            };

            const handleLogin = async (e) => {
                e.preventDefault();
                const pwd = passRef.current.value;
                try {
                    const res = await fetch('?action=verify_password', { method: 'POST', body: JSON.stringify({password: pwd}) });
                    const r = await res.json();
                    if(r.status === 'success') { 
                        setIsAdmin(true); 
                        localStorage.setItem('zen_auth', 'true'); 
                        setShowLogin(false);
                        
                        if (window.location.search.includes('ids=')) {
                            window.history.replaceState(null, '', window.location.pathname);
                            setIsSharedView(false);
                        }

                        setToast({message: '登录成功', type: 'success'}); 
                        loadScripts(); 
                    } 
                    else setToast({message: '密码错误', type: 'error'});
                } catch { alert('登录请求失败'); }
            };

            const isLocked = !isAdmin && !isSharedView;

            const filteredScripts = useMemo(() => {
                const params = new URLSearchParams(window.location.search);
                const sharedIds = params.get('ids') ? params.get('ids').split(',') : null;
                
                if (isLocked) return [];

                let data = scripts;
                if (sharedIds && sharedIds.length > 0) data = scripts.filter(s => sharedIds.includes(s.id));
                
                if (!searchQuery) return data;
                const lower = searchQuery.toLowerCase();
                return data.filter(s => s.title.toLowerCase().includes(lower) || (s.description||'').toLowerCase().includes(lower) || s.tags.some(t=>t.toLowerCase().includes(lower)));
            }, [scripts, searchQuery, isLocked, isSharedView]);

            const toggleSelect = (id) => setSelectedIds(p=>p.includes(id)?p.filter(i=>i!==id):[...p,id]);

            return (
                <div className="min-h-screen pb-32 relative overflow-x-hidden">
                    <AnimatePresence>
                        {hoveredImage && (
                            <motion.div initial={{ opacity: 0, scale: 0.9 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.9 }} transition={{ type: "spring", stiffness: 300, damping: 25 }} className="fixed inset-0 z-[500] pointer-events-none flex items-center justify-center">
                                <div className="relative bg-white p-2 rounded-2xl shadow-2xl border border-white/50 max-w-[90vw] max-h-[80vh] overflow-hidden">
                                    <img src={hoveredImage} className="max-w-full max-h-[75vh] object-contain rounded-xl" />
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    {needsSetup && (
                        <div className="fixed inset-0 z-[300] bg-gray-900/50 backdrop-blur-xl flex items-center justify-center p-4">
                            <motion.div initial={{scale:0.9}} animate={{scale:1}} className="bg-white rounded-3xl p-8 w-full max-w-md shadow-2xl text-center">
                                <div className="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4"><Icon name="shield-check" size={32}/></div>
                                <h2 className="text-2xl font-bold mb-2">系统初始化</h2>
                                <p className="text-gray-500 mb-6">请设置管理员密码。</p>
                                <form onSubmit={handleSetup}>
                                    <input ref={setupPassRef} type="password" placeholder="设置新密码" className="w-full p-4 bg-gray-100 rounded-xl mb-4 text-center text-lg outline-none focus:ring-2 ring-blue-500/20" autoFocus />
                                    <button className="w-full py-4 bg-ios-dark text-white rounded-xl font-bold">完成设置</button>
                                </form>
                            </motion.div>
                        </div>
                    )}

                    <header className="sticky top-0 z-50 pt-6 pb-4 px-6 md:px-12 backdrop-blur-heavy bg-ios-bg/80 border-b border-white/50">
                        <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4">
                            <div className="flex items-center gap-3 flex-shrink-0">
                                <div className="w-10 h-10 bg-ios-dark rounded-xl flex items-center justify-center text-white shadow-lg"><Icon name="terminal" size={20} className="text-white"/></div>
                                <div><h1 className="text-xl font-extrabold tracking-tight text-gray-900">Zen Shell</h1><p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Library</p></div>
                            </div>
                            <div className={`relative w-full md:w-96 transition-opacity ${isLocked ? 'opacity-50 pointer-events-none' : ''}`}>
                                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400"><Icon name="search" size={16}/></div>
                                <input onChange={(e)=>setSearchQuery(e.target.value)} placeholder="搜索脚本..." className="block w-full pl-10 pr-3 py-2.5 border-none rounded-xl bg-gray-200/50 text-gray-900 focus:bg-white/80 transition-all text-sm font-medium"/>
                            </div>
                            <div className="flex items-center gap-3 flex-shrink-0">
                                {isAdmin && <button onClick={()=>{setEditingScript(null);setShowEditor(true)}} className="flex items-center gap-2 px-4 py-2 bg-ios-dark text-white rounded-full text-sm font-semibold shadow-lg hover:bg-black active:scale-95 transition-all whitespace-nowrap"><Icon name="plus" size={16} className="text-white"/><span>新建</span></button>}
                                <button onClick={isAdmin ? ()=>{setIsAdmin(false);localStorage.removeItem('zen_auth');setToast({message:'已注销',type:'success'});setScripts([]);} : ()=>setShowLogin(true)} className={`w-10 h-10 flex items-center justify-center rounded-full shadow-md border transition-all active:scale-95 ${isAdmin ? 'bg-white text-gray-900' : 'bg-ios-dark text-white border-transparent'}`}><Icon name={isAdmin ? "log-out" : "user"} size={20} className={isAdmin ? "" : "text-white"} /></button>
                            </div>
                        </div>
                    </header>

                    <main className="max-w-7xl mx-auto px-6 md:px-12 pt-8 relative min-h-[60vh]">
                        {isLocked && !needsSetup && (
                            <div className="absolute inset-0 z-40 flex flex-col items-center justify-center pt-20">
                                <div className="p-8 bg-white/80 backdrop-blur-2xl rounded-[32px] shadow-2xl border border-white/60 text-center max-w-sm mx-4">
                                    <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-gray-400"><Icon name="lock" size={32}/></div>
                                    <h3 className="text-xl font-bold text-gray-900 mb-2">访问受限</h3>
                                    <p className="text-gray-500 text-sm mb-6">仅管理员可见。请登录或使用分享链接。</p>
                                    <button onClick={()=>setShowLogin(true)} className="px-8 py-3 bg-ios-dark text-white rounded-full font-bold shadow-lg active:scale-95">登录</button>
                                </div>
                            </div>
                        )}
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8 pb-12">
                            <AnimatePresence>
                                {filteredScripts.map(script => (
                                    <ScriptCard 
                                        key={script.id} script={script} isAdmin={isAdmin} isSelected={selectedIds.includes(script.id)}
                                        toggleSelect={toggleSelect} onEdit={(s)=>{setEditingScript(s);setShowEditor(true);}}
                                        onDelete={(id)=>saveData(scripts.filter(s=>s.id!==id))} onImageHover={(src) => setHoveredImage(src)}
                                    />
                                ))}
                            </AnimatePresence>
                        </div>
                    </main>

                    <AnimatePresence>
                        {selectedIds.length > 0 && (
                            <motion.div 
                                initial={{y:100, x: "-50%"}} 
                                animate={{y:0, x: "-50%"}} 
                                exit={{y:100, x: "-50%"}} 
                                transition={{ duration: 0.15, ease: "easeOut" }}
                                className="fixed bottom-8 left-1/2 z-[100] w-fit max-w-[90vw] bg-ios-dark text-white rounded-full shadow-2xl flex items-center gap-4 sm:gap-6 px-6 py-4 cursor-pointer hover:scale-105"
                                onClick={()=>setShowShare(true)}
                            >
                                <span className="font-bold whitespace-nowrap text-sm sm:text-base flex-shrink-0">{selectedIds.length} 个已选</span>
                                <div className="h-4 w-[1px] bg-white/20 hidden sm:block flex-shrink-0"></div>
                                <div className="flex items-center gap-2 whitespace-nowrap flex-shrink-0">
                                    <span className="text-sm font-medium">生成分享</span>
                                    <div className="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center"><Icon name="share" size={14} className="text-white"/></div>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    {showShare && (
                        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                            <div className="absolute inset-0 bg-gray-900/20 backdrop-blur-sm" onClick={()=>setShowShare(false)}/>
                            <motion.div initial={{scale:0.9,opacity:0}} animate={{scale:1,opacity:1}} className="relative w-full max-w-md bg-[#F9F9FB] rounded-[32px] shadow-2xl border border-white/60 p-8 text-center space-y-4">
                                <h3 className="text-lg font-bold text-gray-900">专属分享链接</h3>
                                <div className="p-4 bg-gray-100 rounded-xl break-all font-mono text-xs text-gray-500 select-all border border-gray-200">{window.location.href.split('?')[0] + '?ids=' + selectedIds.join(',')}</div>
                                <button onClick={()=>{navigator.clipboard.writeText(window.location.href.split('?')[0] + '?ids=' + selectedIds.join(','));setToast({message:'链接已复制',type:'success'});setShowShare(false)}} className="w-full py-3 bg-blue-600 text-white rounded-xl font-bold shadow-lg hover:bg-blue-700 active:scale-95 transition-all">复制链接</button>
                            </motion.div>
                        </div>
                    )}

                    {showLogin && (
                        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                            <div className="absolute inset-0 bg-gray-900/20 backdrop-blur-sm" onClick={()=>setShowLogin(false)}/>
                            <motion.div initial={{scale:0.9,opacity:0}} animate={{scale:1,opacity:1}} className="relative w-full max-w-sm bg-[#F9F9FB] rounded-[32px] shadow-2xl border border-white/60 p-8">
                                <h3 className="text-lg font-bold text-gray-900 mb-6">管理员登录</h3>
                                <form onSubmit={handleLogin} className="flex flex-col gap-4">
                                    <input ref={passRef} type="password" placeholder="请输入密码" className="w-full p-4 bg-gray-100 rounded-xl shadow-inner outline-none focus:bg-white focus:ring-2 ring-blue-500/20" autoFocus />
                                    <button className="w-full py-3 bg-ios-dark text-white rounded-xl font-bold shadow-lg active:scale-95 transition-transform">验证身份</button>
                                </form>
                            </motion.div>
                        </div>
                    )}

                    {showEditor && (
                        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4">
                            <div className="absolute inset-0 bg-gray-900/20 backdrop-blur-sm" onClick={()=>setShowEditor(false)}/>
                            <motion.div initial={{y:20,opacity:0}} animate={{y:0,opacity:1}} className="relative w-full max-w-lg bg-[#F9F9FB] rounded-[32px] shadow-2xl overflow-hidden border border-white/60 flex flex-col max-h-[90vh]">
                                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200/50 bg-white/50 backdrop-blur-md">
                                    <h3 className="text-lg font-bold text-gray-900 flex-1 truncate pr-4">{editingScript?"编辑脚本":"添加脚本"}</h3>
                                    <button onClick={()=>setShowEditor(false)} className="w-8 h-8 flex items-center justify-center bg-gray-200/50 rounded-full hover:bg-gray-300/50 transition-colors flex-shrink-0"><Icon name="x" size={18}/></button>
                                </div>
                                <div className="p-6 overflow-y-auto">
                                    <form onSubmit={(e)=>{
                                        e.preventDefault();
                                        const fd=new FormData(e.target);
                                        const tags=fd.get('tags').split(/[,，]/).map(t=>t.trim()).filter(t=>t);
                                        if(tags.length>3)return alert('最多3个标签');
                                        const ns={
                                            id:editingScript?editingScript.id:Math.random().toString(36).substr(2,9),
                                            title:fd.get('title'),command:fd.get('command'),description:fd.get('description'),image:fd.get('image'),tags:tags,
                                            source:{name:fd.get('sourceName'),url:fd.get('sourceUrl')},wrapCode:fd.get('wrapCode')==='on',createdAt:Date.now()
                                        };
                                        saveData(editingScript?scripts.map(s=>s.id===ns.id?ns:s):[ns,...scripts]);
                                        setShowEditor(false);
                                    }} className="flex flex-col gap-5">
                                        <div className="space-y-1.5"><label className="text-xs font-bold text-gray-400 uppercase ml-1">标题</label><input name="title" defaultValue={editingScript?.title} required className="w-full p-3.5 bg-gray-100 rounded-xl shadow-inner-light border border-transparent outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/></div>
                                        <div className="space-y-1.5"><label className="text-xs font-bold text-gray-400 uppercase ml-1">命令 (Bash)</label><textarea name="command" defaultValue={editingScript?.command} required rows="4" className="w-full p-3.5 bg-[#2d2d2d] text-green-400 font-mono text-sm rounded-xl shadow-inner outline-none"/><label className="flex items-center gap-2 mt-2 cursor-pointer w-fit"><input type="checkbox" name="wrapCode" defaultChecked={editingScript?.wrapCode} className="w-4 h-4 rounded border-gray-300 text-black focus:ring-black"/><span className="text-xs text-gray-500 font-medium">自动换行展示</span></label></div>
                                        <div className="space-y-1.5"><label className="text-xs font-bold text-gray-400 uppercase ml-1">简介</label><textarea name="description" defaultValue={editingScript?.description} rows="2" className="w-full p-3.5 bg-gray-100 rounded-xl shadow-inner-light border border-transparent outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/></div>
                                        <div className="grid grid-cols-2 gap-4"><div><label className="text-xs font-bold text-gray-400 uppercase ml-1 block mb-1">标签 <span className="text-gray-400 font-normal opacity-70 ml-1 text-[10px]">(用逗号分隔)</span></label><input name="tags" defaultValue={editingScript?.tags.join(', ')} className="w-full p-3.5 bg-gray-100 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/></div><div><label className="text-xs font-bold text-gray-400 uppercase ml-1 block mb-1">封面URL</label><input name="image" defaultValue={editingScript?.image} className="w-full p-3.5 bg-gray-100 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/></div></div>
                                        <div className="grid grid-cols-2 gap-4"><input name="sourceName" defaultValue={editingScript?.source?.name} placeholder="来源名" className="p-3.5 bg-gray-100 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/><input name="sourceUrl" defaultValue={editingScript?.source?.url} placeholder="来源链接" className="p-3.5 bg-gray-100 rounded-xl outline-none focus:bg-white focus:ring-2 focus:ring-blue-500/10 transition-all"/></div>
                                        <button className="w-full py-3.5 bg-ios-dark text-white rounded-xl font-bold shadow-lg mt-2 hover:scale-[1.01] active:scale-95 transition-all">保存更改</button>
                                    </form>
                                </div>
                            </motion.div>
                        </div>
                    )}
                    {toast && <Toast message={toast.message} type={toast.type} onClose={()=>setToast(null)}/>}
                </div>
            );
        };
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>