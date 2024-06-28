from qiskit import QuantumCircuit
from qiskit.primitives import Sampler
from qiskit.visualization import plot_histogram
import sys
import numpy as np

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
    qc.h(0)
    qc.z(0)
    qc.h(0)
    return

def x_gate_identity():
    qc = QuantumCircuit(1)
    x_gate(qc)
    check(qc, "x_gate_identity")
    return

# CHECKPOINT 2: Write an identity for the Toffoli gate.
def toffoli(qc):
    qc.h(2)
    qc.cx(0, 2)
    qc.tdg(2)
    qc.cx(1, 2)
    qc.t(2)
    qc.cx(0, 2)
    qc.tdg(2)
    qc.cx(1, 2)
    qc.t(2)
    qc.tdg(0)
    qc.h(2)
    qc.cx(1, 0)
    qc.tdg(0)
    qc.cx(1, 0)
    qc.s(0)
    qc.t(1)
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
    
    qc = QuantumCircuit(3)
    qc.h(0)
    toffoli(qc)
    qc.h(0)
    check(qc, "toffoli_identity")
    
    qc = QuantumCircuit(3)
    qc.h(0)
    toffoli(qc)
    qc.h(0)
    check(qc, "toffoli_identity_no_z")
    
    # qc = QuantumCircuit(3)
    # qc.h([0, 1])
    # toffoli(qc)
    # qc.h([0, 1])
    # check(qc, "toffoli_identity_no_z")
    
    return

# CHECKPOINT 3: Write an identity for the Toffoli gate without any explicit Z rotations (Z, S, T, etc.)
def toffoli_no_z(qc):
    qc.h(2)
    qc.cx(0, 2)
    qc.h(2)
    qc.rx(-np.pi/4, 2)
    qc.h(2)
    qc.cx(1, 2)
    qc.h(2)
    qc.rx(np.pi/4, 2)
    qc.h(2)
    qc.cx(0, 2)
    qc.h(2)
    qc.rx(-np.pi/4, 2)
    qc.h(2)
    qc.cx(1, 2)
    qc.h(2)
    qc.rx(np.pi/4, 2)
    qc.h(2)
    qc.h(0)
    qc.rx(-np.pi/4, 0)
    qc.h(0)
    qc.h(2)
    qc.cx(1, 0)
    qc.h(0)
    qc.rx(-np.pi/4, 0)
    qc.h(0)
    qc.cx(1, 0)
    qc.h(0)
    qc.rx(np.pi/2, 0)
    qc.h(0)
    qc.h(1)
    qc.rx(np.pi/4, 1)
    qc.h(1)
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
    
    qc = QuantumCircuit(3)
    qc.h(0)
    toffoli(qc)
    qc.h(0)
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