import { ChatCompletionCreateParamsNonStreaming } from 'openai/resources/index.mjs';
import { RequestOptions } from 'openai/core';
import OpenAI from 'openai';

type Input = Record<string, unknown>;
const Task = {
  create_coursework: 'create coursework',
  create_rubric: 'create rubric',
  assess_coursework: 'assess coursework',
} as const;

type Params = {
  task: keyof typeof Task;
  input: Input;
  responseType: Record<string, unknown>;
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

export function createCompletion(
  body: ChatCompletionCreateParamsNonStreaming,
  options?: RequestOptions
) {
  const openai = new OpenAI({
    organization: process.env.OPENAI_ORG_ID,
    apiKey: process.env.OPENAI_API_KEY,
    timeout: 1000 * 60 * 1, // 1 minute
  });
  return openai.chat.completions.create(body, options);
}
