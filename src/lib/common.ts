export function errorToJSON(error: unknown) {
  if (error instanceof Error) {
    return error.message;
  }

  return JSON.stringify(error);
}

export const toJSON = (content: string | undefined | null) => {
  if (content) {
    try {
      const value = content.replace(/"/g, `\"`).replace(/'/g, `\'`);
      return JSON.parse(value);
    } catch (error) {
      console.error(error);
    }
  }

  return {};
};
