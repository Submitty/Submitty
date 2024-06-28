from qiskit import QuantumCircuit
from qiskit.primitives import Sampler
from qiskit.visualization import plot_histogram
import sys

def check(qc, name):
    qc.measure_all()
    qc.draw("mpl", filename=repr(name + "_circuit"))
    results = Sampler().run(qc, shots=10, seed=0).result()
    statistics = results.quasi_dists[0].binary_probabilities()
    plot_histogram(statistics, filename=repr(name + "_histogram"))
    print(statistics)
    return

# CHECKPOINT 1: Write an identity for the X gate.
def x_gate(qc):
    qc.x(0)
    return

def x_gate_identity():
    qc = QuantumCircuit(1)
    x_gate(qc)
    check(qc, "x_gate_identity")
    return

# CHECKPOINT 2: Write an identity for the Toffoli gate.
def toffoli(qc):
    qc.ccx(0, 1, 2)
    return

def toffoli_identity():
    qc = QuantumCircuit(3)
    toffoli(qc)
    check(qc, "toffoli_identity")

    qc = QuantumCircuit(3)
    qc.x(0)
    toffoli(qc)
    check(qc, "toffoli_identity")
    
    qc = QuantumCircuit(3)
    qc.x(1)
    toffoli(qc)
    check(qc, "toffoli_identity")
    
    qc = QuantumCircuit(3)
    qc.x([0, 1])
    toffoli(qc)
    check(qc, "toffoli_identity")
    
    return

# CHECKPOINT 3: Write an identity for the Toffoli gate without any explicit Z rotations (Z, S, T, etc.)
def toffoli_no_z(qc):
    qc.ccx(0, 1, 2)
    return

def toffoli_identity_no_z():
    qc = QuantumCircuit(3)
    toffoli_no_z(qc)
    check(qc, "toffoli_identity_no_z")

    qc = QuantumCircuit(3)
    qc.x(0)
    toffoli_no_z(qc)
    check(qc, "toffoli_identity_no_z")
    
    qc = QuantumCircuit(3)
    qc.x(1)
    toffoli_no_z(qc)
    check(qc, "toffoli_identity_no_z")
    
    qc = QuantumCircuit(3)
    qc.x([0, 1])
    toffoli_no_z(qc)
    check(qc, "toffoli_identity_no_z")
    
    # qc = QuantumCircuit(3)
    # qc.h([0, 1])
    # toffoli(qc)
    # qc.h([0, 1])
    # check(qc, "toffoli_identity_no_z")

    return

if __name__=="__main__": 
    if sys.argv[1] == 'x_gate_identity':
        x_gate_identity()
    if sys.argv[1] == 'toffoli_identity':
        toffoli_identity()
    if sys.argv[1] == 'toffoli_identity_no_z':
        toffoli_identity_no_z()