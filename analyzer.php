<?php
class StackAnalyzer {
    private $path;
    private $data = [
        'environment' => [],
        'framework' => 'Unknown',
        'php_extensions' => [],
        'composer' => ['php_version' => 'Not Specified', 'require' => [], 'require_dev' => []],
        'npm' => ['require' => [], 'require_dev' => []],
        'features' => []
    ];

    public function __construct($path) {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function analyze() {
        $this->analyzeEnvironment();
        $this->parseComposer();
        $this->parseNpm();
        $this->detectFramework();
        $this->checkFeatures();
        return $this->data;
    }

    private function analyzeEnvironment() {
        $this->data['environment'] = [
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
            'php_version' => phpversion(),
            'env_file_exists' => file_exists($this->path . DIRECTORY_SEPARATOR . '.env')
        ];
    }

    private function parseComposer() {
        $file = $this->path . DIRECTORY_SEPARATOR . 'composer.json';
        if (!file_exists($file)) return;
        
        $json = json_decode(file_get_contents($file), true);
        if (isset($json['require']['php'])) $this->data['composer']['php_version'] = $json['require']['php'];
        
        foreach (['require', 'require-dev'] as $key) {
            if (isset($json[$key])) {
                $target = str_replace('-', '_', $key);
                foreach ($json[$key] as $pkg => $ver) {
                    if (strpos($pkg, 'ext-') === 0) {
                        $extName = substr($pkg, 4);
                        $this->data['php_extensions'][$extName] = extension_loaded($extName);
                    } else {
                        $this->data['composer'][$target][$pkg] = $ver;
                    }
                }
            }
        }
    }

    private function parseNpm() {
        $file = $this->path . DIRECTORY_SEPARATOR . 'package.json';
        if (!file_exists($file)) return;
        
        $json = json_decode(file_get_contents($file), true);
        $this->data['npm']['require'] = $json['dependencies'] ?? [];
        $this->data['npm']['require_dev'] = $json['devDependencies'] ?? [];
    }

    private function detectFramework() {
        $cReq = $this->data['composer']['require'];
        $nReq = $this->data['npm']['require'];
        
        $frameworks = [
            'laravel/framework' => 'Laravel',
            'symfony/framework-bundle' => 'Symfony',
            'codeigniter4/framework' => 'CodeIgniter 4',
            'cakephp/cakephp' => 'CakePHP',
            'next' => 'Next.js (Node)',
            'nuxt' => 'Nuxt.js (Node)',
            'react' => 'React.js',
            'vue' => 'Vue.js'
        ];

        foreach ($frameworks as $pkg => $name) {
            if (array_key_exists($pkg, $cReq) || array_key_exists($pkg, $nReq)) {
                $this->data['framework'] = $name;
                return;
            }
        }

        if (file_exists($this->path . DIRECTORY_SEPARATOR . 'wp-config.php')) $this->data['framework'] = 'WordPress';
        elseif (file_exists($this->path . DIRECTORY_SEPARATOR . 'artisan')) $this->data['framework'] = 'Laravel';
    }

    private function checkFeatures() {
        $this->data['features']['docker'] = file_exists($this->path . DIRECTORY_SEPARATOR . 'Dockerfile');
        $this->data['features']['git'] = is_dir($this->path . DIRECTORY_SEPARATOR . '.git');
        $this->data['features']['webpack'] = file_exists($this->path . DIRECTORY_SEPARATOR . 'webpack.mix.js') || file_exists($this->path . DIRECTORY_SEPARATOR . 'webpack.config.js');
        $this->data['features']['vite'] = file_exists($this->path . DIRECTORY_SEPARATOR . 'vite.config.js');
    }
}

$analyzer = new StackAnalyzer(__DIR__);
$result = $analyzer->analyze();

if (isset($_GET['api']) && filter_var($_GET['api'], FILTER_VALIDATE_BOOLEAN)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['path' => realpath(__DIR__), 'analysis' => $result]);
    exit;
}

function renderList($items, $isExt = false) {
    if (empty($items)) return '<div class="empty">None found</div>';
    $html = '<ul class="pkg-list">';
    foreach ($items as $name => $val) {
        if ($isExt) {
            $status = $val ? '<span class="badge success">Installed</span>' : '<span class="badge danger">Missing</span>';
            $html .= '<li><strong>' . htmlspecialchars($name) . '</strong> ' . $status . '</li>';
        } else {
            $html .= '<li><strong>' . htmlspecialchars($name) . '</strong> <code>' . htmlspecialchars($val) . '</code></li>';
        }
    }
    $html .= '</ul>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Stack Analyzer</title>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --border: #30363d; --text: #c9d1d9; --accent: #58a6ff; --success: #238636; --danger: #da3633; }
        body { font-family: system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 20px; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { margin-bottom: 30px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .header h1 { margin: 0; color: var(--accent); font-size: 1.8rem; }
        .path { color: #8b949e; font-size: 0.9rem; margin-top: 5px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .card-header { background: rgba(255,255,255,0.02); padding: 15px 20px; border-bottom: 1px solid var(--border); font-weight: bold; font-size: 1.1rem; }
        .card-body { padding: 20px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px dashed var(--border); padding-bottom: 5px; }
        .info-row:last-child { border: none; margin: 0; padding: 0; }
        .pkg-list { list-style: none; padding: 0; margin: 0; max-height: 250px; overflow-y: auto; }
        .pkg-list li { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); }
        .pkg-list li:last-child { border: none; }
        code { background: rgba(88,166,255,0.1); color: var(--accent); padding: 2px 6px; border-radius: 4px; font-size: 0.85em; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; color: #fff; }
        .badge.success { background: var(--success); }
        .badge.danger { background: var(--danger); }
        .empty { color: #8b949e; font-style: italic; text-align: center; padding: 20px; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #484f58; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Advanced Stack Analyzer</h1>
            <div class="path"><?php echo htmlspecialchars($analyzer->analyze()['environment']['os']); ?> | <?php echo htmlspecialchars(realpath(__DIR__)); ?></div>
        </div>

        <div class="grid">
            <div class="card">
                <div class="card-header">🚀 Core Environment</div>
                <div class="card-body">
                    <div class="info-row"><span>Framework</span> <strong><?php echo htmlspecialchars($result['framework']); ?></strong></div>
                    <div class="info-row"><span>Server PHP</span> <code><?php echo htmlspecialchars($result['environment']['php_version']); ?></code></div>
                    <div class="info-row"><span>Project PHP</span> <code><?php echo htmlspecialchars($result['composer']['php_version']); ?></code></div>
                    <div class="info-row"><span>Web Server</span> <span><?php echo htmlspecialchars($result['environment']['server']); ?></span></div>
                    <div class="info-row"><span>.env File</span> <?php echo $result['environment']['env_file_exists'] ? '<span class="badge success">Found</span>' : '<span class="badge danger">Missing</span>'; ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">🧩 PHP Extensions Check</div>
                <div class="card-body" style="padding:0 20px;">
                    <?php echo renderList($result['php_extensions'], true); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">📦 Composer Packages</div>
                <div class="card-body" style="padding:0 20px;">
                    <?php echo renderList($result['composer']['require']); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">🌐 NPM / Node Packages</div>
                <div class="card-body" style="padding:0 20px;">
                    <?php echo renderList($result['npm']['require']); ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">🛠 Project Features</div>
                <div class="card-body">
                    <?php foreach ($result['features'] as $feat => $exists): ?>
                        <div class="info-row">
                            <span style="text-transform: capitalize;"><?php echo $feat; ?></span> 
                            <?php echo $exists ? '<span class="badge success">Yes</span>' : '<span class="badge danger">No</span>'; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
