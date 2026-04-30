(function () {
    function bootDashboard() {
    // The dashboard keeps mock data in memory so each interaction updates the UI like a real workflow, without a backend.
    const mockClusters = [
        {
            id: 'C-101',
            name: 'Timeout-heavy recursion',
            label: 'Cluster C-101',
            size: 18,
            passRate: 24,
            commonIssue: 'Timeout',
            status: 'normal',
            failureTypes: ['Timeout', 'Infinite loop', 'Extra logging'],
            signals: ['Median runtime 41s', 'Two long-running recursion paths', 'One submission missing the memoized branch'],
            parentId: 'G-1',
            members: [
                { studentId: 's1024', anonId: 'A104', score: 84, adjustment: 0, feedback: '', snippet: 'while (n > 0) {\n    total += solve(n);\n}', tests: ['timeout case', 'depth-10 recursion'], passRate: 25 },
                { studentId: 's1182', anonId: 'A117', score: 78, adjustment: 0, feedback: '', snippet: 'for (int i = 0; i <= n; i++) {\n    answer += dfs(i);\n}', tests: ['large input', 'cycle guard'], passRate: 20 },
                { studentId: 's1199', anonId: 'A121', score: 80, adjustment: 0, feedback: '', snippet: 'if (cache.count(key)) return cache[key];\nreturn solve(key - 1);', tests: ['cache hit', 'recursive limit'], passRate: 28 },
            ],
        },
        {
            id: 'C-102',
            name: 'Boundary logic drift',
            label: 'Cluster C-102',
            size: 14,
            passRate: 68,
            commonIssue: 'Off-by-one',
            status: 'normal',
            failureTypes: ['Boundary error', 'Incorrect loop limit'],
            signals: ['Most failures disappear after adjusting one loop bound', 'High pass rate on sample tests', 'Consistent mismatch on last index'],
            parentId: 'G-1',
            members: [
                { studentId: 's1214', anonId: 'A127', score: 88, adjustment: 0, feedback: '', snippet: 'for (int i = 0; i < items.size() - 1; i++) {\n    sum += items[i];\n}', tests: ['final index', 'empty list'], passRate: 66 },
                { studentId: 's1231', anonId: 'A130', score: 91, adjustment: 0, feedback: '', snippet: 'if (pos <= limit) {\n    push(value);\n}', tests: ['boundary edge', 'limit inclusive'], passRate: 71 },
                { studentId: 's1245', anonId: 'A132', score: 86, adjustment: 0, feedback: '', snippet: 'return index == arr.length ? arr[index - 1] : arr[index];', tests: ['upper bound', 'tail access'], passRate: 67 },
            ],
        },
        {
            id: 'C-103',
            name: 'Missing null guards',
            label: 'Cluster C-103',
            size: 11,
            passRate: 52,
            commonIssue: 'Missing null check',
            status: 'normal',
            failureTypes: ['Null dereference', 'Assumption on optional field'],
            signals: ['Failure pattern appears after optional submission data is absent', 'Most code paths otherwise pass', 'Two submissions share the same crash signature'],
            parentId: 'G-2',
            members: [
                { studentId: 's1302', anonId: 'A141', score: 74, adjustment: 0, feedback: '', snippet: 'if (user.profile.email.trim().length > 0) {\n    send();\n}', tests: ['missing profile', 'email blank'], passRate: 49 },
                { studentId: 's1308', anonId: 'A143', score: 77, adjustment: 0, feedback: '', snippet: 'const label = options.choice.value;\nreturn label.toString();', tests: ['undefined choice', 'optional input'], passRate: 55 },
                { studentId: 's1311', anonId: 'A145', score: 72, adjustment: 0, feedback: '', snippet: 'return record.details.title.length > 0;', tests: ['empty details', 'missing record'], passRate: 53 },
            ],
        },
        {
            id: 'C-104',
            name: 'Output formatting noise',
            label: 'Cluster C-104',
            size: 16,
            passRate: 83,
            commonIssue: 'Formatting / output noise',
            status: 'normal',
            failureTypes: ['Whitespace mismatch', 'Extra debug output'],
            signals: ['Logic is correct but output requires cleanup', 'Most failures are presentation-only', 'High consistency across test cases'],
            parentId: 'G-2',
            members: [
                { studentId: 's1324', anonId: 'A147', score: 93, adjustment: 0, feedback: '', snippet: 'printf("Result: %d\\n", answer);\nstd::cout << debug << std::endl;', tests: ['stdout compare', 'hidden whitespace'], passRate: 82 },
                { studentId: 's1330', anonId: 'A149', score: 92, adjustment: 0, feedback: '', snippet: 'console.log("done");\nreturn value;', tests: ['no debug output', 'newline compare'], passRate: 85 },
                { studentId: 's1337', anonId: 'A151', score: 90, adjustment: 0, feedback: '', snippet: 'System.out.print(value + " ");\nSystem.out.flush();', tests: ['trailing spaces', 'exact formatting'], passRate: 83 },
            ],
        },
        {
            id: 'C-105',
            name: 'Index range misuse',
            label: 'Cluster C-105',
            size: 9,
            passRate: 31,
            commonIssue: 'Array bounds',
            status: 'normal',
            failureTypes: ['Index error', 'Unexpected empty array'],
            signals: ['Recurring out-of-range access on shared helper', 'Small cluster but tight similarity', 'Candidate for a quick split'],
            parentId: 'G-3',
            members: [
                { studentId: 's1401', anonId: 'A160', score: 68, adjustment: 0, feedback: '', snippet: 'for (let i = 0; i <= arr.length; i++) {\n    out.push(arr[i]);\n}', tests: ['empty array', 'last slot'], passRate: 30 },
                { studentId: 's1404', anonId: 'A161', score: 70, adjustment: 0, feedback: '', snippet: 'return values[index + 1];', tests: ['index zero', 'final element'], passRate: 33 },
                { studentId: 's1407', anonId: 'A163', score: 66, adjustment: 0, feedback: '', snippet: 'if (pos > items.length) return null;', tests: ['upper bound'], passRate: 31 },
            ],
        },
        {
            id: 'C-106',
            name: 'Rubric mismatch outlier',
            label: 'Cluster C-106',
            size: 12,
            passRate: 74,
            commonIssue: 'Partial credit mismatch',
            status: 'rejected',
            failureTypes: ['Scoring mismatch', 'Rubric disagreement'],
            signals: ['Cluster rejected for review because it reflects rubric disagreement rather than a shared code smell', 'Feedback already manually curated', 'Keep hidden from auto-suggestions'],
            parentId: 'G-3',
            members: [
                { studentId: 's1419', anonId: 'A170', score: 81, adjustment: 0, feedback: '', snippet: 'return score + 2;', tests: ['manual override'], passRate: 75 },
                { studentId: 's1422', anonId: 'A171', score: 79, adjustment: 0, feedback: '', snippet: 'points += reviewerBonus;', tests: ['rubric comparison'], passRate: 73 },
                { studentId: 's1428', anonId: 'A172', score: 83, adjustment: 0, feedback: '', snippet: 'finalScore = rawScore;', tests: ['scale normalization'], passRate: 74 },
            ],
        },
    ];

    const templates = [
        {
            id: 'timeout',
            label: 'Likely timeout issue',
            title: 'Timeout hypothesis',
            body: 'This cluster shows signs of repeated recursion or runtime growth. Re-check loop bounds, memoization, and early exits.',
        },
        {
            id: 'boundary',
            label: 'Missing boundary check',
            title: 'Boundary check hypothesis',
            body: 'The cluster is consistent with an off-by-one or range validation issue. Review the last index, empty input, and inclusive ranges.',
        },
        {
            id: 'null',
            label: 'Missing null check',
            title: 'Null safety hypothesis',
            body: 'The submissions appear to assume optional data exists. Add guards before dereferencing nested fields.',
        },
        {
            id: 'format',
            label: 'Formatting / output cleanup',
            title: 'Presentation cleanup',
            body: 'The algorithm is mostly correct, but output formatting or debug text is still causing hidden-test failures.',
        },
    ];

    const smartSuggestions = {
        'Likely timeout issue': 'timeout',
        'Missing boundary check': 'boundary',
        'Off-by-one error': 'boundary',
    };

    const state = {
        clusters: mockClusters,
        selectedClusterId: mockClusters[0].id,
        selectedMergeIds: new Set([mockClusters[0].id]),
        activeFilter: 'all',
        gradingMode: 'bulk',
        feedbackTemplateId: 'timeout',
        splitHeight: 58,
        toastTimer: null,
        nextClusterNumber: 201,
    };

    const elements = {
        clusterList: document.getElementById('cluster-list'),
        dendrogram: document.getElementById('dendrogram-svg'),
        tooltip: document.getElementById('dendrogram-tooltip'),
        feedbackTemplateSelect: document.getElementById('feedback-template-select'),
        feedbackPreview: document.getElementById('feedback-preview'),
        applyFeedbackButton: document.getElementById('apply-feedback-btn'),
        editableFeedbackPanel: document.getElementById('editable-feedback-panel'),
        gradingModePanel: document.getElementById('grading-mode-panel'),
        comparison: document.getElementById('submission-comparison'),
        mergeModal: document.getElementById('merge-modal'),
        mergeModalCopy: document.getElementById('merge-modal-copy'),
        confirmMergeButton: document.getElementById('confirm-merge-btn'),
        rejectModal: document.getElementById('reject-modal'),
        rejectReasonInput: document.getElementById('reject-reason'),
        rejectModalReason: document.getElementById('reject-modal-reason'),
        confirmRejectButton: document.getElementById('confirm-reject-btn'),
        toast: document.getElementById('ai-toast'),
        splitHeight: document.getElementById('split-height'),
        splitPreview: document.getElementById('split-preview'),
        mergeSelectionCopy: document.getElementById('merge-selection-copy'),
        selectedClusterTitle: document.getElementById('selected-cluster-title'),
        selectedClusterStatus: document.getElementById('selected-cluster-status'),
        signalList: document.getElementById('signal-list'),
        detailsSplitButton: document.getElementById('details-split-btn'),
        detailsMergeButton: document.getElementById('details-merge-btn'),
        detailsRejectButton: document.getElementById('details-reject-btn'),
    };

    if (!elements.clusterList || !elements.dendrogram || !elements.gradingModePanel || !elements.feedbackPreview) {
        return;
    }

    if (elements.feedbackTemplateSelect) {
        templates.forEach((template) => {
            const option = document.createElement('option');
            option.value = template.id;
            option.textContent = template.label;
            elements.feedbackTemplateSelect.appendChild(option);
        });
        elements.feedbackTemplateSelect.value = state.feedbackTemplateId;
    }

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function formatPercent(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? `${parsed.toFixed(0)}%` : 'N/A';
    }

    function displayClusterName(cluster) {
        return cluster?.name || cluster?.id || 'Unknown cluster';
    }

    function showToast(message) {
        elements.toast.textContent = message;
        elements.toast.hidden = false;
        clearTimeout(state.toastTimer);
        state.toastTimer = window.setTimeout(() => {
            elements.toast.hidden = true;
        }, 2400);
    }

    function openModal(modal) {
        modal.hidden = false;
    }

    function closeModal(modal) {
        modal.hidden = true;
    }

    function createClusterId() {
        const id = `C-${state.nextClusterNumber}`;
        state.nextClusterNumber += 1;
        return id;
    }

    function getCluster(clusterId) {
        return state.clusters.find((cluster) => cluster.id === clusterId) ?? null;
    }

    function getTemplate(templateId) {
        return templates.find((template) => template.id === templateId) ?? templates[0];
    }

    function getLeafClusters() {
        return state.clusters.filter((cluster) => cluster.status !== 'archived');
    }

    function calculatePassRate(members, fallback) {
        if (!Array.isArray(members) || members.length === 0) {
            return fallback;
        }
        return members.reduce((sum, member) => sum + Number(member.passRate || 0), 0) / members.length;
    }

    function buildTree() {
        const nodes = [
            { id: 'ROOT', label: 'Root', parentId: null, kind: 'root', children: [] },
            { id: 'G-1', label: 'Group 1', parentId: 'ROOT', kind: 'group', children: [] },
            { id: 'G-2', label: 'Group 2', parentId: 'ROOT', kind: 'group', children: [] },
            { id: 'G-3', label: 'Group 3', parentId: 'ROOT', kind: 'group', children: [] },
        ];

        state.clusters.forEach((cluster) => {
            nodes.push({ id: cluster.id, label: cluster.id, parentId: cluster.parentId, kind: 'cluster', children: [] });
        });

        const byId = new Map(nodes.map((node) => [node.id, node]));
        nodes.forEach((node) => {
            if (node.parentId && byId.has(node.parentId)) {
                byId.get(node.parentId).children.push(node.id);
            }
        });

        return nodes;
    }

    function collectClusterDescendants(nodeId, nodesById) {
        const node = nodesById.get(nodeId);
        if (!node) {
            return [];
        }
        if (node.kind === 'cluster') {
            return [node.id];
        }
        return node.children.flatMap((childId) => collectClusterDescendants(childId, nodesById));
    }

    function aggregateNode(nodeId, nodesById) {
        const descendantIds = collectClusterDescendants(nodeId, nodesById);
        const descendants = descendantIds.map((id) => getCluster(id)).filter(Boolean);

        if (descendants.length === 0) {
            return null;
        }

        const size = descendants.reduce((sum, cluster) => sum + cluster.size, 0);
        const passRate = descendants.reduce((sum, cluster) => sum + cluster.passRate, 0) / descendants.length;
        const issueCounts = new Map();
        descendants.forEach((cluster) => {
            issueCounts.set(cluster.commonIssue, (issueCounts.get(cluster.commonIssue) ?? 0) + 1);
        });

        const dominantIssue = [...issueCounts.entries()].sort((a, b) => b[1] - a[1])[0]?.[0] ?? 'Mixed';
        return {
            size,
            passRate,
            issue: dominantIssue,
            failureTypes: [...new Set(descendants.flatMap((cluster) => cluster.failureTypes))],
        };
    }

    function findFirstCluster(nodeId, nodesById) {
        const descendantIds = collectClusterDescendants(nodeId, nodesById);
        return descendantIds[0] ?? state.selectedClusterId;
    }

    function getNodeMetrics(nodeId, nodesById) {
        const node = nodesById.get(nodeId);
        if (!node) {
            return null;
        }

        if (node.kind === 'cluster') {
            const cluster = getCluster(node.id);
            if (!cluster) {
                return null;
            }
            return {
                title: cluster.id,
                size: cluster.size,
                passRate: cluster.passRate,
                issue: cluster.commonIssue,
                failureTypes: cluster.failureTypes,
                status: cluster.status,
            };
        }

        const aggregate = aggregateNode(nodeId, nodesById);
        if (!aggregate) {
            return null;
        }

        return {
            title: node.label,
            size: aggregate.size,
            passRate: aggregate.passRate,
            issue: aggregate.issue,
            failureTypes: aggregate.failureTypes,
            status: 'normal',
        };
    }

    function computeLayout() {
        // SVG keeps the dendrogram crisp and makes hover/select targets stable at any screen size.
        const nodes = buildTree();
        const byId = new Map(nodes.map((node) => [node.id, node]));
        const leafNodes = nodes.filter((node) => node.kind === 'cluster');
        const svgWidth = Math.max(760, Math.round(elements.dendrogram.getBoundingClientRect().width || 760));
        const chartPadding = 64;
        const leafStep = (svgWidth - chartPadding * 2) / (leafNodes.length + 1);
        const levels = { 0: 30, 1: 96, 2: 184, 3: 278 };

        leafNodes.forEach((node, index) => {
            node.x = chartPadding + ((index + 1) * leafStep);
            node.y = levels[3];
        });

        function positionNode(nodeId, depth) {
            const node = byId.get(nodeId);
            if (!node) {
                return { x: 0, y: 0 };
            }

            if (node.kind === 'cluster') {
                return { x: node.x, y: node.y };
            }

            const childPositions = node.children.map((childId) => positionNode(childId, depth + 1));
            const x = childPositions.reduce((sum, child) => sum + child.x, 0) / childPositions.length;
            node.x = x;
            node.y = levels[depth] ?? 48;
            return { x, y: node.y };
        }

        positionNode('ROOT', 0);
        return { nodes, byId, svgWidth };
    }

    function updateSummary() {
        const visibleClusters = state.clusters.filter((cluster) => state.activeFilter === 'all' || cluster.status === state.activeFilter);
        const clusterCount = visibleClusters.length;
        const submissionCount = visibleClusters.reduce((sum, cluster) => sum + cluster.size, 0);
        const avgPassRate = visibleClusters.length > 0
            ? visibleClusters.reduce((sum, cluster) => sum + cluster.passRate, 0) / visibleClusters.length
            : 0;
        const flaggedCount = state.clusters.filter((cluster) => cluster.status === 'rejected').length;

        document.querySelector('[data-summary="cluster-count"]').textContent = String(clusterCount);
        document.querySelector('[data-summary="submission-count"]').textContent = String(submissionCount);
        document.querySelector('[data-summary="avg-pass-rate"]').textContent = formatPercent(avgPassRate);
        document.querySelector('[data-summary="flagged-count"]').textContent = String(flaggedCount);
    }

    function renderClusterList() {
        elements.clusterList.replaceChildren();

        const visibleClusters = getLeafClusters().filter((cluster) => state.activeFilter === 'all' || cluster.status === state.activeFilter);

        visibleClusters.forEach((cluster) => {
            const card = document.createElement('article');
            card.className = `ai-cluster-card${cluster.id === state.selectedClusterId ? ' is-selected' : ''}${cluster.status === 'rejected' ? ' is-rejected' : ''}`;
            card.dataset.clusterId = cluster.id;

            card.innerHTML = [
                '<div class="ai-cluster-card-top">',
                '  <div>',
                `    <h3>${escapeHtml(displayClusterName(cluster))}</h3>`,
                `    <p>${escapeHtml(cluster.id)} · ${escapeHtml(cluster.commonIssue)}</p>`,
                '  </div>',
                `  <label aria-label="Select ${escapeHtml(cluster.id)} for merge">`,
                `    <input type="checkbox" data-merge-checkbox="${escapeHtml(cluster.id)}" ${state.selectedMergeIds.has(cluster.id) ? 'checked' : ''}>`,
                '  </label>',
                '</div>',
                '<div class="status-line">',
                `  <span class="ai-status-badge">${cluster.status === 'rejected' ? 'Rejected' : 'Normal'}</span>`,
                `${cluster.status === 'rejected' ? '<span class="ai-warning-icon" aria-label="Rejected cluster">!</span>' : ''}`,
                '</div>',
                `<div class="meta-row"><span>Submissions</span><strong>${cluster.size}</strong></div>`,
                `<div class="meta-row"><span>Avg pass rate</span><strong>${formatPercent(cluster.passRate)}</strong></div>`,
                `<div class="meta-row"><span>Issue</span><strong>${escapeHtml(cluster.commonIssue)}</strong></div>`,
            ].join('');

            card.addEventListener('click', (event) => {
                if (event.target instanceof HTMLInputElement) {
                    return;
                }
                selectCluster(cluster.id);
            });

            const checkbox = card.querySelector('[data-merge-checkbox]');
            if (checkbox instanceof HTMLInputElement) {
                checkbox.addEventListener('change', () => {
                    toggleMergeSelection(cluster.id, checkbox.checked);
                });
            }

            elements.clusterList.appendChild(card);
        });
    }

    function renderDendrogram() {
        const { nodes, byId, svgWidth } = computeLayout();
        const svg = elements.dendrogram;
        svg.setAttribute('viewBox', `0 0 ${svgWidth} 320`);
        svg.replaceChildren();

        const root = byId.get('ROOT');
        if (root) {
            const rootLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            rootLabel.setAttribute('x', String(svgWidth / 2));
            rootLabel.setAttribute('y', '18');
            rootLabel.setAttribute('class', 'ai-node-label');
            rootLabel.textContent = 'Model space';
            svg.appendChild(rootLabel);
        }

        nodes.forEach((node) => {
            if (node.id === 'ROOT') {
                return;
            }

            const parent = byId.get(node.parentId);
            if (parent) {
                const vertical = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                vertical.setAttribute('x1', String(parent.x));
                vertical.setAttribute('y1', String(parent.y + 12));
                vertical.setAttribute('x2', String(parent.x));
                vertical.setAttribute('y2', String(node.y - 18));
                vertical.setAttribute('class', 'ai-link');
                svg.appendChild(vertical);

                const horizontal = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                horizontal.setAttribute('x1', String(Math.min(parent.x, node.x)));
                horizontal.setAttribute('y1', String(node.y - 18));
                horizontal.setAttribute('x2', String(Math.max(parent.x, node.x)));
                horizontal.setAttribute('y2', String(node.y - 18));
                horizontal.setAttribute('class', 'ai-link');
                svg.appendChild(horizontal);
            }
        });

        nodes.forEach((node) => {
            if (node.id === 'ROOT') {
                return;
            }

            const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
            const selected = node.kind === 'cluster' && node.id === state.selectedClusterId;
            const rejected = node.kind === 'cluster' && getCluster(node.id)?.status === 'rejected';
            group.setAttribute('class', `ai-node${selected ? ' is-selected' : ''}${rejected ? ' is-rejected' : ''}`);
            group.setAttribute('transform', `translate(${node.x}, ${node.y})`);
            group.dataset.nodeId = node.id;

            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('r', node.kind === 'group' ? '18' : '24');
            circle.setAttribute('class', 'ai-node-circle');
            group.appendChild(circle);

            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('class', 'ai-node-label');
            label.setAttribute('y', node.kind === 'group' ? '5' : '4');
            label.textContent = node.kind === 'group' ? 'G' : node.id.replace('C-', '');
            group.appendChild(label);

            if (node.kind === 'cluster') {
                const subtitle = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                subtitle.setAttribute('class', 'ai-node-subtitle');
                subtitle.setAttribute('y', '40');
                subtitle.textContent = formatPercent(getCluster(node.id)?.passRate ?? 0);
                group.appendChild(subtitle);
            }

            group.addEventListener('click', () => {
                selectTreeNode(node.id, byId);
            });
            group.addEventListener('mouseenter', (event) => {
                showTooltip(node.id, byId, event);
            });
            group.addEventListener('mousemove', (event) => moveTooltip(event));
            group.addEventListener('mouseleave', hideTooltip);

            svg.appendChild(group);
        });
    }

    function moveTooltip(event) {
        const rect = elements.dendrogram.getBoundingClientRect();
        const x = Math.min(event.clientX - rect.left + 14, rect.width - 260);
        const y = Math.min(event.clientY - rect.top + 14, rect.height - 120);
        elements.tooltip.style.left = `${Math.max(12, x)}px`;
        elements.tooltip.style.top = `${Math.max(12, y)}px`;
    }

    function showTooltip(nodeId, byId, event) {
        const metrics = getNodeMetrics(nodeId, byId);
        if (!metrics) {
            hideTooltip();
            return;
        }

        const node = byId.get(nodeId);
        const cluster = node && node.kind === 'cluster' ? getCluster(node.id) : null;

        elements.tooltip.hidden = false;
        elements.tooltip.innerHTML = `
            <strong>${escapeHtml(cluster ? `${displayClusterName(cluster)} (${cluster.id})` : metrics.title)}</strong>
            <p>Size: ${metrics.size} submissions</p>
            <p>Pass rate: ${formatPercent(metrics.passRate)}</p>
            <p>Failure types: ${metrics.failureTypes.map(escapeHtml).join(', ')}</p>
        `;
        moveTooltip(event);
    }

    function hideTooltip() {
        elements.tooltip.hidden = true;
    }

    function selectTreeNode(nodeId, byId) {
        const node = byId.get(nodeId);
        if (!node) {
            return;
        }

        const nextClusterId = node.kind === 'cluster' ? node.id : findFirstCluster(nodeId, byId);
        state.selectedClusterId = nextClusterId;
        state.selectedMergeIds.add(nextClusterId);
        renderAll();
        showToast(`Selected ${nextClusterId}`);
    }

    function selectCluster(clusterId) {
        state.selectedClusterId = clusterId;
        state.selectedMergeIds.add(clusterId);
        renderAll();
        showToast(`Selected ${clusterId}`);
    }

    function toggleMergeSelection(clusterId, isSelected) {
        if (isSelected) {
            state.selectedMergeIds.add(clusterId);
        }
        else {
            state.selectedMergeIds.delete(clusterId);
        }
        renderMergeCopy();
        renderDendrogram();
    }

    function renderSelectedDetails() {
        const cluster = getCluster(state.selectedClusterId) ?? state.clusters[0];
        if (!cluster) {
            return;
        }

        elements.selectedClusterTitle.textContent = `${displayClusterName(cluster)} (${cluster.id})`;
        elements.selectedClusterStatus.textContent = cluster.status === 'rejected' ? 'Rejected' : 'Normal';
        elements.selectedClusterStatus.style.background = cluster.status === 'rejected' ? '#f7dedd' : 'rgba(255,255,255,0.8)';
        elements.selectedClusterStatus.style.borderColor = cluster.status === 'rejected' ? 'rgba(159, 61, 54, 0.26)' : '#d8dee7';

        document.querySelector('[data-detail="size"]').textContent = String(cluster.size);
        document.querySelector('[data-detail="pass-rate"]').textContent = formatPercent(cluster.passRate);
        document.querySelector('[data-detail="issue"]').textContent = cluster.commonIssue;
        document.querySelector('[data-detail="failures"]').textContent = cluster.failureTypes.join(', ');

        elements.signalList.replaceChildren();
        cluster.signals.forEach((signal) => {
            const item = document.createElement('li');
            item.textContent = signal;
            elements.signalList.appendChild(item);
        });

        renderSplitPreview();
        renderComparison();
        renderFeedbackPreview();
        renderEditableFeedback();
    }

    function renderMergeCopy() {
        const selected = [...state.selectedMergeIds]
            .map((clusterId) => getCluster(clusterId))
            .filter(Boolean)
            .filter((cluster) => cluster.status !== 'rejected');

        if (selected.length === 0) {
            elements.mergeSelectionCopy.textContent = 'No clusters selected.';
            return;
        }

        const total = selected.reduce((sum, cluster) => sum + cluster.size, 0);
        const avgPassRate = selected.reduce((sum, cluster) => sum + cluster.passRate, 0) / selected.length;
        elements.mergeSelectionCopy.textContent = `Selected ${selected.length} clusters, ${total} submissions total, projected pass rate ${formatPercent(avgPassRate)}.`;
    }

    function renderSplitPreview() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster) {
            elements.splitPreview.textContent = 'Select a cluster to preview the cut.';
            return;
        }

        const leftSize = Math.max(1, Math.round(cluster.size * (state.splitHeight / 100)));
        const rightSize = Math.max(1, cluster.size - leftSize);
        elements.splitPreview.innerHTML = `
            <div class="ai-split-card">
                <h4>Preview: ${escapeHtml(cluster.id)}</h4>
                <p>Cut height ${state.splitHeight} would separate this cluster into two groups of roughly ${leftSize} and ${rightSize} submissions.</p>
                <p>Most likely split boundary: ${escapeHtml(cluster.commonIssue.toLowerCase())} versus outliers.</p>
            </div>
        `;
    }

    function renderFeedbackPreview() {
        const cluster = getCluster(state.selectedClusterId);
        const template = getTemplate(state.feedbackTemplateId);
        const previewMembers = cluster ? cluster.members.slice(0, 3) : [];

        elements.feedbackPreview.replaceChildren();
        previewMembers.forEach((member) => {
            const card = document.createElement('article');
            card.className = 'ai-preview-card';
            card.innerHTML = `
                <h4>${escapeHtml(member.studentId)}</h4>
                <p><strong>${escapeHtml(template.title)}:</strong> ${escapeHtml(template.body)}</p>
                <p>Suggested tie-in: ${escapeHtml(cluster.commonIssue)}</p>
            `;
            elements.feedbackPreview.appendChild(card);
        });
    }

    function renderEditableFeedback() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster || !cluster.feedbackApplied) {
            elements.editableFeedbackPanel.hidden = true;
            elements.editableFeedbackPanel.replaceChildren();
            return;
        }

        elements.editableFeedbackPanel.hidden = false;
        elements.editableFeedbackPanel.replaceChildren();

        cluster.members.forEach((member) => {
            const row = document.createElement('article');
            row.className = 'ai-feedback-row';
            row.innerHTML = `
                <div class="status-line">
                    <strong>${escapeHtml(member.studentId)}</strong>
                    <span class="ai-status-badge">${escapeHtml(member.anonId)}</span>
                </div>
                <textarea>${escapeHtml(member.feedback || getTemplate(state.feedbackTemplateId).body)}</textarea>
            `;

            const textarea = row.querySelector('textarea');
            if (textarea instanceof HTMLTextAreaElement) {
                textarea.addEventListener('input', () => {
                    member.feedback = textarea.value;
                });
            }

            elements.editableFeedbackPanel.appendChild(row);
        });
    }

    function renderGradingPanel() {
        const cluster = getCluster(state.selectedClusterId);
        const members = cluster ? cluster.members : [];

        elements.gradingModePanel.replaceChildren();

        if (state.gradingMode === 'bulk') {
            const wrapper = document.createElement('div');
            wrapper.className = 'ai-grading-tabs-wrap';
            wrapper.innerHTML = `
                <label class="ai-field">
                    <span>Score</span>
                    <input type="number" id="bulk-score-input" min="0" max="100" value="${members[0]?.score ?? 80}">
                </label>
                <button type="button" class="ai-primary-action full-width" id="apply-bulk-score-btn">Apply to all</button>
                <p class="ai-muted-copy">Bulk mode is useful when the cluster is stable and the whole group receives the same score.</p>
            `;

            const button = wrapper.querySelector('#apply-bulk-score-btn');
            const input = wrapper.querySelector('#bulk-score-input');
            if (button instanceof HTMLButtonElement && input instanceof HTMLInputElement) {
                button.addEventListener('click', () => {
                    members.forEach((member) => {
                        member.score = Number(input.value);
                    });
                    showToast(`Applied ${input.value} to all submissions in ${cluster.id}`);
                    renderComparison();
                });
            }

            elements.gradingModePanel.appendChild(wrapper);
            return;
        }

        if (state.gradingMode === 'individual') {
            const wrapper = document.createElement('div');
            wrapper.className = 'ai-grading-grid';

            members.forEach((member) => {
                const row = document.createElement('article');
                row.className = 'ai-grading-card';
                row.innerHTML = `
                    <h4>${escapeHtml(member.studentId)} / ${escapeHtml(member.anonId)}</h4>
                    <p>Submission preview: ${escapeHtml(member.snippet.slice(0, 80))}...</p>
                    <label class="ai-field">
                        <span>Score</span>
                        <input type="number" min="0" max="100" value="${member.score}">
                    </label>
                `;

                const input = row.querySelector('input');
                if (input instanceof HTMLInputElement) {
                    input.addEventListener('input', () => {
                        member.score = Number(input.value);
                    });
                }

                wrapper.appendChild(row);
            });

            elements.gradingModePanel.appendChild(wrapper);
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'ai-grading-tabs-wrap';
        wrapper.innerHTML = `
            <label class="ai-field">
                <span>Base score</span>
                <input type="number" id="hybrid-base-score" min="0" max="100" value="${members[0]?.score ?? 80}">
            </label>
        `;

        const baseScoreInput = wrapper.querySelector('#hybrid-base-score');
        const adjustments = document.createElement('div');
        adjustments.className = 'ai-grading-grid';

        members.forEach((member) => {
            const row = document.createElement('article');
            row.className = 'ai-grading-card';
            row.innerHTML = `
                <h4>${escapeHtml(member.studentId)}</h4>
                <div class="ai-grading-input-row">
                    <div>
                        <p>Base score</p>
                        <strong>${member.score}</strong>
                    </div>
                    <label class="ai-field">
                        <span>Adjustment</span>
                        <input type="number" value="${member.adjustment}" min="-20" max="20">
                    </label>
                    <div>
                        <p>Result</p>
                        <strong data-result>—</strong>
                    </div>
                </div>
            `;

            const adjustmentInput = row.querySelector('input');
            const result = row.querySelector('[data-result]');

            const updateResult = () => {
                if (!(adjustmentInput instanceof HTMLInputElement) || !(baseScoreInput instanceof HTMLInputElement) || !(result instanceof HTMLElement)) {
                    return;
                }

                const base = Number(baseScoreInput.value);
                const adjustment = Number(adjustmentInput.value);
                member.adjustment = adjustment;
                result.textContent = String(Math.max(0, Math.min(100, base + adjustment)));
            };

            if (adjustmentInput instanceof HTMLInputElement) {
                adjustmentInput.addEventListener('input', updateResult);
            }
            if (baseScoreInput instanceof HTMLInputElement) {
                baseScoreInput.addEventListener('input', updateResult);
            }

            updateResult();
            adjustments.appendChild(row);
        });

        wrapper.appendChild(adjustments);
        elements.gradingModePanel.appendChild(wrapper);
    }

    function renderComparison() {
        const cluster = getCluster(state.selectedClusterId);
        const members = cluster ? cluster.members.slice(0, 3) : [];

        elements.comparison.replaceChildren();
        members.forEach((member) => {
            const card = document.createElement('article');
            card.className = 'ai-comparison-card';
            card.innerHTML = `
                <h4>${escapeHtml(member.studentId)}</h4>
                <p>Pass rate in cluster: ${formatPercent(member.passRate)}</p>
                <div class="ai-code-block">${escapeHtml(member.snippet)}</div>
                <div class="ai-test-list">
                    <strong>Test results</strong>
                    <p>${escapeHtml(member.tests.join(' · '))}</p>
                </div>
                <p>Current score: <strong>${member.score}</strong></p>
            `;
            elements.comparison.appendChild(card);
        });
    }

    function renderMergeCopy() {
        const selected = [...state.selectedMergeIds]
            .map((clusterId) => getCluster(clusterId))
            .filter(Boolean)
            .filter((cluster) => cluster.status !== 'rejected');

        if (selected.length === 0) {
            elements.mergeSelectionCopy.textContent = 'No clusters selected.';
            return;
        }

        const total = selected.reduce((sum, cluster) => sum + cluster.size, 0);
        const avgPassRate = selected.reduce((sum, cluster) => sum + cluster.passRate, 0) / selected.length;
        elements.mergeSelectionCopy.textContent = `Selected ${selected.length} clusters, ${total} submissions total, projected pass rate ${formatPercent(avgPassRate)}.`;
    }

    function applyFeedbackTemplate() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster) {
            return;
        }

        const template = getTemplate(state.feedbackTemplateId);
        cluster.feedbackApplied = true;
        cluster.members.forEach((member) => {
            member.feedback = `${template.body} Follow up on ${cluster.commonIssue.toLowerCase()}.`;
        });

        showToast(`Applied ${template.label} to ${cluster.id}`);
        renderEditableFeedback();
    }

    function mergeSelectedClusters() {
        const selected = [...state.selectedMergeIds]
            .map((clusterId) => getCluster(clusterId))
            .filter(Boolean)
            .filter((cluster) => cluster.status !== 'rejected');

        if (selected.length < 2) {
            showToast('Select at least two normal clusters to merge.');
            return;
        }

        const total = selected.reduce((sum, cluster) => sum + cluster.size, 0);
        const avgPassRate = selected.reduce((sum, cluster) => sum + cluster.passRate, 0) / selected.length;
        const issues = [...new Set(selected.map((cluster) => cluster.commonIssue))].join(' + ');
        elements.mergeModalCopy.textContent = `Merge ${selected.map((cluster) => cluster.id).join(', ')} into one grouped recommendation? This would stage ${total} submissions with a projected pass rate of ${formatPercent(avgPassRate)} and the shared issue pattern ${issues}.`;
        openModal(elements.mergeModal);
    }

    function confirmMerge() {
        const selected = [...state.selectedMergeIds]
            .map((clusterId) => getCluster(clusterId))
            .filter(Boolean)
            .filter((cluster) => cluster.status !== 'rejected');

        if (selected.length < 2) {
            closeModal(elements.mergeModal);
            return;
        }

        const mergedMembers = selected.flatMap((cluster) => cluster.members);
        const mergedSize = selected.reduce((sum, cluster) => sum + cluster.size, 0);
        const mergedPassRate = selected.reduce((sum, cluster) => sum + cluster.passRate, 0) / selected.length;
        const mergedFailureTypes = [...new Set(selected.flatMap((cluster) => cluster.failureTypes))];
        const mergedIssue = [...new Set(selected.map((cluster) => cluster.commonIssue))].join(' + ');

        const mergedCluster = {
            id: createClusterId(),
            label: `Merged ${selected.length}-cluster set`,
            size: mergedSize,
            passRate: mergedPassRate,
            commonIssue: mergedIssue,
            status: 'normal',
            failureTypes: mergedFailureTypes,
            signals: ['Generated by merge action from dashboard prototype', ...selected.flatMap((cluster) => cluster.signals).slice(0, 4)],
            parentId: selected[0].parentId,
            members: mergedMembers,
            feedbackApplied: false,
            rejectedReason: '',
            adjustment: 0,
        };

        selected.forEach((cluster) => {
            cluster.status = 'archived';
        });

        state.clusters.push(mergedCluster);
        state.selectedClusterId = mergedCluster.id;
        state.selectedMergeIds = new Set([mergedCluster.id]);

        renderAll();
        showToast(`Merged into ${mergedCluster.id}.`);
        closeModal(elements.mergeModal);
    }

    function splitSelectedCluster() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster) {
            return;
        }

        state.splitHeight = Number(elements.splitHeight.value);
        const ratio = Math.max(0.1, Math.min(0.9, state.splitHeight / 100));
        const members = [...cluster.members];

        if (members.length < 2) {
            showToast('Not enough submissions to split this cluster.');
            return;
        }

        const splitIndex = Math.max(1, Math.min(members.length - 1, Math.round(members.length * ratio)));
        const leftMembers = members.slice(0, splitIndex);
        const rightMembers = members.slice(splitIndex);

        const leftSize = Math.max(1, Math.round(cluster.size * ratio));
        const rightSize = Math.max(1, cluster.size - leftSize);

        const leftCluster = {
            ...cluster,
            id: createClusterId(),
            label: `${cluster.label} A`,
            size: leftSize,
            passRate: calculatePassRate(leftMembers, cluster.passRate),
            members: leftMembers,
            status: 'normal',
            commonIssue: `${cluster.commonIssue} (core)`,
            signals: [...cluster.signals, 'Generated by split action from dashboard prototype'],
            feedbackApplied: false,
            rejectedReason: '',
        };

        const rightCluster = {
            ...cluster,
            id: createClusterId(),
            label: `${cluster.label} B`,
            size: rightSize,
            passRate: calculatePassRate(rightMembers, cluster.passRate),
            members: rightMembers,
            status: 'normal',
            commonIssue: `${cluster.commonIssue} (outliers)`,
            signals: [...cluster.signals, 'Generated by split action from dashboard prototype'],
            feedbackApplied: false,
            rejectedReason: '',
        };

        cluster.status = 'archived';
        state.clusters.push(leftCluster, rightCluster);
        state.selectedClusterId = leftCluster.id;
        state.selectedMergeIds = new Set([leftCluster.id, rightCluster.id]);

        renderAll();
        showToast(`Split ${cluster.id} into ${leftCluster.id} and ${rightCluster.id}.`);
    }

    function rejectSelectedCluster() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster) {
            return;
        }

        elements.rejectModalReason.value = cluster.rejectedReason || '';
        elements.rejectReasonInput.value = cluster.rejectedReason || '';
        openModal(elements.rejectModal);
    }

    function confirmReject() {
        const cluster = getCluster(state.selectedClusterId);
        if (!cluster) {
            return;
        }

        const reason = elements.rejectModalReason.value.trim() || elements.rejectReasonInput.value.trim() || 'Rejected after manual review.';
        cluster.status = 'rejected';
        cluster.rejectedReason = reason;
        closeModal(elements.rejectModal);
        showToast(`Rejected ${cluster.id}.`);
        renderAll();
    }

    function bindEvents() {
        elements.feedbackTemplateSelect?.addEventListener('change', (event) => {
            const select = event.currentTarget;
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            state.feedbackTemplateId = select.value;
            renderFeedbackPreview();
            renderEditableFeedback();
        });

        elements.applyFeedbackButton?.addEventListener('click', applyFeedbackTemplate);
        elements.splitHeight?.addEventListener('input', splitSelectedCluster);

        document.querySelectorAll('[data-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                const filter = button.getAttribute('data-filter');
                if (!filter) {
                    return;
                }

                state.activeFilter = filter;
                document.querySelectorAll('[data-filter]').forEach((btn) => btn.classList.toggle('is-active', btn === button));
                renderAll();
            });
        });

        document.querySelectorAll('[data-suggestion]').forEach((button) => {
            button.addEventListener('click', () => {
                const suggestion = button.getAttribute('data-suggestion');
                const templateId = suggestion ? smartSuggestions[suggestion] : null;
                if (!templateId) {
                    return;
                }

                state.feedbackTemplateId = templateId;
                elements.feedbackTemplateSelect.value = templateId;
                renderFeedbackPreview();
                showToast(`${suggestion} loaded into feedback template.`);
            });
        });

        document.querySelectorAll('.ai-mode-tab').forEach((button) => {
            button.addEventListener('click', () => {
                const mode = button.getAttribute('data-mode');
                if (!mode) {
                    return;
                }

                state.gradingMode = mode;
                document.querySelectorAll('.ai-mode-tab').forEach((btn) => btn.classList.toggle('is-active', btn === button));
                renderGradingPanel();
            });
        });

        document.querySelectorAll('[data-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-action');
                if (action === 'merge') {
                    mergeSelectedClusters();
                }
                else if (action === 'split') {
                    splitSelectedCluster();
                }
                else if (action === 'reject') {
                    rejectSelectedCluster();
                }
            });
        });

        elements.detailsMergeButton?.addEventListener('click', mergeSelectedClusters);
        elements.detailsSplitButton?.addEventListener('click', splitSelectedCluster);
        elements.detailsRejectButton?.addEventListener('click', rejectSelectedCluster);
        elements.confirmMergeButton?.addEventListener('click', confirmMerge);
        elements.confirmRejectButton?.addEventListener('click', confirmReject);

        document.querySelectorAll('[data-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(elements.mergeModal);
                closeModal(elements.rejectModal);
            });
        });

        [elements.mergeModal, elements.rejectModal].forEach((modal) => {
            if (!modal) {
                return;
            }
            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (elements.mergeModal) {
                    closeModal(elements.mergeModal);
                }
                if (elements.rejectModal) {
                    closeModal(elements.rejectModal);
                }
            }
        });
    }

    function renderAll() {
        updateSummary();
        renderClusterList();
        renderDendrogram();
        renderSelectedDetails();
        renderMergeCopy();
        renderGradingPanel();
        renderSplitPreview();
        renderFeedbackPreview();
        renderEditableFeedback();
    }

    function initialRender() {
        if (elements.mergeModal) {
            closeModal(elements.mergeModal);
            elements.mergeModal.setAttribute('hidden', 'hidden');
        }
        if (elements.rejectModal) {
            closeModal(elements.rejectModal);
            elements.rejectModal.setAttribute('hidden', 'hidden');
        }
        state.splitHeight = Number(elements.splitHeight.value);
        renderAll();
        bindEvents();
    }

    initialRender();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootDashboard);
    }
    else {
        bootDashboard();
    }
}());