import pkgutil
import importlib

__all__ = []
for loader, module_name, is_pkg in pkgutil.iter_modules(__path__):
    __all__.append(module_name)
    module = importlib.import_module(f"{__name__}.{module_name}")
    globals()[module_name] = module
