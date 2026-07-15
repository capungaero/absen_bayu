import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ReactFlow, Background, Controls, MiniMap,
  useNodesState, useEdgesState, addEdge,
} from '@xyflow/react';
import { nodeTypes } from './nodes.jsx';

const EDGE_STYLE = { stroke: '#334155', strokeWidth: 2 };
const ANIMATED_EDGE = { ...EDGE_STYLE, animated: true };

function buildGraph(result, customItems, onItemClick) {
  if (!result) return { nodes: [], edges: [] };

  const { employee, income, outcome } = result;
  const activeInsentif = income.insentifList.filter(x => x.active);
  const activeDeductions = outcome.deductionList.filter(x => x.active);
  const cBonus  = customItems.filter(x => x.type === 'bonus');
  const cDeduct = customItems.filter(x => x.type === 'deduction');
  const customBonusTotal  = cBonus.reduce((s, x) => s + (Number(x.amount)||0), 0);
  const customDeductTotal = cDeduct.reduce((s, x) => s + (Number(x.amount)||0), 0);

  const incomeTotal  = income.gajiPokok + income.overtime + income.insentif + customBonusTotal;
  const outcomeTotal = outcome.fine + outcome.deduction + outcome.strip + customDeductTotal;
  const thp  = Math.max(0, incomeTotal - outcomeTotal);
  const debt = incomeTotal - outcomeTotal < 0 ? Math.abs(incomeTotal - outcomeTotal) : 0;

  const CX = 300;
  const nodes = [
    {
      id: 'emp', type: 'employee', position: { x: CX - 90, y: 0 },
      data: { name: employee.name, position: employee.position, branch: employee.branch, masaKerja: employee.masaKerja, ptkpStatus: employee.ptkpStatus },
    },
    {
      id: 'salary', type: 'salary', position: { x: CX - 90, y: 170 },
      data: { salary: employee.salary, salaryMin: employee.salaryMin, paymentReceive: income.gajiPokok },
    },
    {
      id: 'income', type: 'income', position: { x: CX - 90, y: 340 },
      data: { overtime: income.overtime, overtimeHours: income.overtimeHours, overtimeLog: income.overtimeLog || [], items: activeInsentif, customBonus: customBonusTotal, total: incomeTotal - income.gajiPokok, onItemClick },
    },
    {
      id: 'outcome', type: 'outcome', position: { x: CX - 90, y: 560 },
      data: { fineList: (outcome.fineList || []).filter(x => x.active), deductions: activeDeductions, strip: outcome.strip, customDeduct: customDeductTotal, total: outcomeTotal, onItemClick },
    },
    {
      id: 'thp', type: 'thp', position: { x: CX - 100, y: 830 },
      data: { thp, debt, totalIncome: incomeTotal, totalOutcome: outcomeTotal },
    },
  ];

  const edges = [
    { id: 'e1', source: 'emp',     target: 'salary',  style: ANIMATED_EDGE },
    { id: 'e2', source: 'salary',  target: 'income',  style: ANIMATED_EDGE },
    { id: 'e3', source: 'income',  target: 'outcome', style: EDGE_STYLE },
    { id: 'e4', source: 'outcome', target: 'thp',     style: ANIMATED_EDGE, markerEnd: { type: 'arrowclosed', color: '#0ea5e9' } },
  ];

  return { nodes, edges };
}

export default function FlowCanvas({ result, customItems, onDrop, onDragOver }) {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const [detailItem, setDetailItem] = useState(null); // { item, parentId, isFine }
  const prevResultRef = useRef(null);

  const handleItemClick = useCallback((item, parentId, isFine) => {
    setDetailItem(prev => prev?.item?.id === item.id ? null : { item, parentId, isFine });
  }, []);

  useEffect(() => {
    // Clear detail panel on new calculation result
    let effectDetail = detailItem;
    if (result !== prevResultRef.current) {
      prevResultRef.current = result;
      effectDetail = null;
      if (detailItem !== null) setDetailItem(null);
    }

    const { nodes: n, edges: e } = buildGraph(result, customItems, handleItemClick);

    if (effectDetail) {
      const parentNode = n.find(nd => nd.id === effectDetail.parentId);
      const px = (parentNode?.position?.x ?? 210) + 260;
      const py = parentNode?.position?.y ?? 400;
      n.push({
        id: 'detail',
        type: 'detail',
        position: { x: px, y: py },
        data: {
          item: effectDetail.item,
          isFine: effectDetail.isFine,
          onClose: () => setDetailItem(null),
        },
      });
      e.push({
        id: 'e-detail',
        source: effectDetail.parentId,
        target: 'detail',
        style: { stroke: '#475569', strokeWidth: 1.5, strokeDasharray: '5 4' },
        markerEnd: { type: 'arrowclosed', color: '#475569' },
      });
    }

    setNodes(n);
    setEdges(e);
  }, [result, customItems, detailItem, handleItemClick]);

  const onConnect = useCallback(
    (params) => setEdges(eds => addEdge({ ...params, style: EDGE_STYLE }, eds)),
    []
  );

  return (
    <div className="canvas-wrap" onDrop={onDrop} onDragOver={onDragOver}>
      {!result && (
        <div className="empty-state">
          <div className="icon">📊</div>
          <p>Cari mitra kerja → pilih periode → klik <strong>Hitung</strong></p>
          <p style={{ fontSize: 12, color: '#1e293b' }}>atau drag item bonus/potongan dari sidebar kiri</p>
        </div>
      )}
      <ReactFlow
        nodes={nodes}
        edges={edges}
        onNodesChange={onNodesChange}
        onEdgesChange={onEdgesChange}
        onConnect={onConnect}
        nodeTypes={nodeTypes}
        fitView
        fitViewOptions={{ padding: 0.2 }}
        minZoom={0.3}
        maxZoom={1.5}
        proOptions={{ hideAttribution: true }}
      >
        <Background color="#1e293b" gap={24} />
        <Controls />
        <MiniMap
          nodeColor={n => {
            if (n.type === 'employee') return '#2563eb';
            if (n.type === 'income')   return '#059669';
            if (n.type === 'outcome')  return '#dc2626';
            if (n.type === 'thp')      return '#0ea5e9';
            if (n.type === 'detail')   return '#475569';
            return '#334155';
          }}
          maskColor="rgba(15,23,42,.7)"
        />
      </ReactFlow>
    </div>
  );
}
