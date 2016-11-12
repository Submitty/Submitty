#include <string>
#include <vector>

class Benchmark {
public:
  Benchmark(const std::string& s);

  friend int NumVisibleBenchmarks();
  friend int WhichVisibleBenchmark(const std::string& s);
  friend void DisplayBenchmark(const std::string& s);
  friend void SetBenchmarkPercentage(const std::string& s, float v);
  friend float GetBenchmarkPercentage(const std::string& s);

  friend void SetBenchmarkColor(const std::string& s, const std::string& c);
  friend const std::string& GetBenchmarkColor(const std::string& s);

private:

  static Benchmark& GetBenchmark(const std::string& s);

  int position;
  std::string name;
  bool visible;
  float percentage;
  std::string color;
  static int next_benchmark_position;
};


int NumVisibleBenchmarks();
int WhichVisibleBenchmark(const std::string& s);
void DisplayBenchmark(const std::string& s);
void SetBenchmarkPercentage(const std::string& s, float v);
float GetBenchmarkPercentage(const std::string& s);

void SetBenchmarkColor(const std::string& s, const std::string& c);
const std::string& GetBenchmarkColor(const std::string& s);


extern std::vector<std::pair<std::string,Benchmark> > DISPLAY_BENCHMARKS;

