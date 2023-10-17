import { ChatCompletionCreateParamsNonStreaming } from 'openai/resources/index.mjs';

type Input = Record<string, unknown>;
type ResponseType = Record<string, 'string' | 'number'>;
const Task = {
  create_coursework: 'create coursework',
  assess_coursework: 'assess coursework',
} as const;

type Params = {
  task: keyof typeof Task;
  input: Input;
  responseType: ResponseType;
};

export const completion = (params: Params) => {
  const task = Task[params.task];
  const response = JSON.stringify(params.responseType);
  return {
    messages: [
      {
        role: 'system',
        content: `You will ${task} based on the provided json payload and respond in json using format: ${response}`,
      },
      {
        role: 'user',
        content: JSON.stringify(params.input),
      },
    ],
    model: 'gpt-3.5-turbo',
  } as ChatCompletionCreateParamsNonStreaming;
};
