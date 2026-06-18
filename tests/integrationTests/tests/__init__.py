import pkgutil

__all__ = []
for loader, module_name, is_pkg in pkgutil.iter_modules(__path__):
    __all__.append(module_name)
    module = loader.find_module(module_name).load_module(module_name)
    exec("%s = module" % module_name)
