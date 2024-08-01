from qiskit import QuantumCircuit
qc = QuantumCircuit(2)
qc.h(1)
qc.cx(1, 0)
print(qc.draw())
