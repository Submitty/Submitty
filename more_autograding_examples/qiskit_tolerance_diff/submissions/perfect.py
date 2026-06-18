from qiskit.visualization import plot_histogram

statistics = {'00': 0.25, '01': 0.25, '10': 0.25, '11': 0.25}
plot_histogram(statistics, filename=str("histogram"), bar_labels=False)
print(statistics)
