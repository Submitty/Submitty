from qiskit import QuantumCircuit
from qiskit.primitives import Sampler
from qiskit.visualization import plot_histogram
import sys

def x_gate_identity():
    qc = QuantumCircuit(1)

    qc.x(0)

    qc.measure_all()
    qc.draw("mpl", filename="x_gate_identity_circuit")
    results = Sampler().run(qc, shots=1, seed=0).result()
    statistics = results.quasi_dists[0].binary_probabilities()
    plot_histogram(statistics, filename="x_gate_identity_histogram")
    print(statistics)
    return

def toffoli_identity():
    qc = QuantumCircuit(3)
    qc.x([0, 1])

    qc.ccx(0, 1, 2)

    qc.measure_all()
    qc.draw("mpl", filename="toffoli_gate_identity_circuit")
    results = Sampler().run(qc, shots=1, seed=0).result()
    statistics = results.quasi_dists[0].binary_probabilities()
    plot_histogram(statistics, filename="toffoli_gate_identity_histogram")
    print(statistics)
    
    return

def toffoli_identity_no_z():
    qc = QuantumCircuit(3)
    qc.x([0, 1])

    qc.ccx(0, 1, 2)

    qc.measure_all()
    qc.draw("mpl", filename="toffoli_gate_no_z_identity_circuit")
    results = Sampler().run(qc, shots=1, seed=0).result()
    statistics = results.quasi_dists[0].binary_probabilities()
    plot_histogram(statistics, filename="toffoli_gate_no_z_identity_histogram")
    print(statistics)
    
    return

if __name__=="__main__": 
    if sys.argv[1] == 'x_gate_identity':
        x_gate_identity()
    if sys.argv[1] == 'toffoli_identity':
        toffoli_identity()
    if sys.argv[1] == 'toffoli_identity_no_z':
        toffoli_identity_no_z()